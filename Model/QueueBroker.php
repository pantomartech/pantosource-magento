<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Math\Random;

class QueueBroker
{
    private const TABLE = 'pantosource_queue';
    private const DL = 'pantosource_dead_letter';
    public const MAX_BYTES = 20000;
    public const MAX_ATTEMPTS = 5;
    public const MAX_ROWS = 500;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Random $random
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(array $payload, string $reason = '', int $storeId = 0): void
    {
        $json = (string) json_encode($payload);
        if (strlen($json) > self::MAX_BYTES) {
            return;
        }
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $publicId = 'q_' . substr(hash('sha256', $this->random->getRandomString(32)), 0, 14);
        $eventId = isset($payload['event']['id']) ? (string) $payload['event']['id'] : '';

        $this->trimQueue($conn, $table);
        $conn->insert($table, [
            'public_id' => $publicId,
            'event_id' => $eventId,
            'payload' => $json,
            'attempts' => 0,
            'next_attempt_at' => time(),
            'last_error' => substr($reason, 0, 65000),
            'store_id' => $storeId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function moveToDeadLetter(string $publicId, array $row): void
    {
        $conn = $this->resource->getConnection();
        $qTable = $this->resource->getTableName(self::TABLE);
        $dlTable = $this->resource->getTableName(self::DL);
        $conn->delete($qTable, ['public_id = ?' => $publicId]);
        $conn->insert($dlTable, [
            'public_id' => $publicId,
            'event_id' => $row['event_id'] ?? '',
            'payload' => $row['payload'] ?? '{}',
            'attempts' => (int) ($row['attempts'] ?? 0),
            'last_error' => substr((string) ($row['last_error'] ?? ''), 0, 65000),
            'store_id' => (int) ($row['store_id'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function replayDeadLetter(string $publicId): bool
    {
        $conn = $this->resource->getConnection();
        $dlTable = $this->resource->getTableName(self::DL);
        $qTable = $this->resource->getTableName(self::TABLE);
        $select = $conn->select()->from($dlTable)->where('public_id = ?', $publicId);
        $dl = $conn->fetchRow($select);
        if (!$dl) {
            return false;
        }
        $conn->delete($dlTable, ['public_id = ?' => $publicId]);
        $conn->insert($qTable, [
            'public_id' => $publicId,
            'event_id' => $dl['event_id'],
            'payload' => $dl['payload'],
            'attempts' => 0,
            'next_attempt_at' => time(),
            'last_error' => 'replayed',
            'store_id' => (int) ($dl['store_id'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchDue(int $limit = 25): array
    {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $now = time();
        $select = $conn->select()
            ->from($table)
            ->where('next_attempt_at <= ?', $now)
            ->order('entity_id ASC')
            ->limit($limit);
        $rows = $conn->fetchAll($select);
        return is_array($rows) ? $rows : [];
    }

    public function deleteByEntityId(int $entityId): void
    {
        $table = $this->resource->getTableName(self::TABLE);
        $this->resource->getConnection()->delete($table, ['entity_id = ?' => $entityId]);
    }

    public function bumpFailure(int $entityId, int $attempts, string $error): void
    {
        $table = $this->resource->getTableName(self::TABLE);
        $delay = min(3600, (int) pow(2, min($attempts, 8)));
        $this->resource->getConnection()->update(
            $table,
            [
                'attempts' => $attempts + 1,
                'next_attempt_at' => time() + $delay,
                'last_error' => substr($error, 0, 65000),
            ],
            ['entity_id = ?' => $entityId]
        );
    }

    private function trimQueue(\Magento\Framework\DB\Adapter\AdapterInterface $conn, string $table): void
    {
        $c = (int) $conn->fetchOne("SELECT COUNT(*) FROM {$table}");
        if ($c < self::MAX_ROWS) {
            return;
        }
        $deleteCount = $c - self::MAX_ROWS + 1;
        $ids = $conn->fetchCol(
            $conn->select()
                ->from($table, 'entity_id')
                ->order('entity_id ASC')
                ->limit($deleteCount)
        );
        if ($ids !== []) {
            $conn->delete($table, ['entity_id IN (?)' => $ids]);
        }
    }

    public function getPendingCount(): int
    {
        $t = $this->resource->getTableName(self::TABLE);
        return (int) $this->resource->getConnection()->fetchOne("SELECT COUNT(*) FROM {$t}");
    }

    public function getDeadLetterCount(): int
    {
        $t = $this->resource->getTableName(self::DL);
        return (int) $this->resource->getConnection()->fetchOne("SELECT COUNT(*) FROM {$t}");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchDeadLetters(int $limit = 50): array
    {
        $conn = $this->resource->getConnection();
        $t = $this->resource->getTableName(self::DL);
        $select = $conn->select()->from($t)->order('entity_id DESC')->limit($limit);
        $rows = $conn->fetchAll($select);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Recent error samples from the retry queue and dead-letter table (for admin diagnostics).
     *
     * @return list<array{surface:string,public_id:string,store_id:int,last_error:string,created_at:string}>
     */
    public function fetchRecentErrorSamples(int $limit = 10): array
    {
        $conn = $this->resource->getConnection();
        $q = $this->resource->getTableName(self::TABLE);
        $dl = $this->resource->getTableName(self::DL);
        $half = max(1, (int) ceil($limit / 2));
        $qSql = $conn->select()
            ->from($q, [
                'public_id',
                'store_id',
                'last_error',
                'created_at',
            ])
            ->where('last_error IS NOT NULL')
            ->where('last_error <> ?', '')
            ->order('entity_id DESC')
            ->limit($half);
        $dlSql = $conn->select()
            ->from($dl, [
                'public_id',
                'store_id',
                'last_error',
                'created_at',
            ])
            ->where('last_error IS NOT NULL')
            ->where('last_error <> ?', '')
            ->order('entity_id DESC')
            ->limit($half);
        $out = [];
        foreach ($conn->fetchAll($qSql) as $row) {
            $out[] = [
                'surface' => 'queue',
                'public_id' => (string) ($row['public_id'] ?? ''),
                'store_id' => (int) ($row['store_id'] ?? 0),
                'last_error' => substr((string) ($row['last_error'] ?? ''), 0, 500),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        foreach ($conn->fetchAll($dlSql) as $row) {
            $out[] = [
                'surface' => 'dead_letter',
                'public_id' => (string) ($row['public_id'] ?? ''),
                'store_id' => (int) ($row['store_id'] ?? 0),
                'last_error' => substr((string) ($row['last_error'] ?? ''), 0, 500),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        return array_slice($out, 0, $limit);
    }
}
