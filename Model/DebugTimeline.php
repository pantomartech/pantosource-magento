<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Math\Random;
use Panto\Source\Api\ConfigInterface;

class DebugTimeline
{
    private const TABLE = 'pantosource_debug_timeline';
    private const MAX = 1000;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ConfigInterface $config,
        private readonly Random $random,
        private readonly DebugAttributionContext $debugAttributionContext
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function append(array $row, ?int $storeId = null): void
    {
        if (!$this->config->isDebugEnabled($storeId)) {
            return;
        }
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $publicId = 'dbg_' . substr(hash('sha256', $this->random->getRandomString(16)), 0, 14);
        $ts = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');

        $conn->insert($table, [
            'public_id' => $publicId,
            'ts' => $ts,
            'source' => substr((string) ($row['source'] ?? ''), 0, 64),
            'hook_or_action' => substr((string) ($row['hook_or_action'] ?? ''), 0, 128),
            'event_name' => substr((string) ($row['event_name'] ?? ''), 0, 128),
            'event_id' => substr((string) ($row['event_id'] ?? ''), 0, 64),
            'status' => substr((string) ($row['status'] ?? ''), 0, 64),
            'context_json' => json_encode($row['context'] ?? [], JSON_THROW_ON_ERROR),
            'payload_json' => json_encode($row['payload'] ?? [], JSON_THROW_ON_ERROR),
        ]);

        $total = (int) $conn->fetchOne("SELECT COUNT(*) FROM {$table}");
        if ($total > self::MAX) {
            $excess = $total - self::MAX;
            $conn->query(
                "DELETE FROM {$table} ORDER BY entity_id ASC LIMIT " . (int) $excess
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRecent(int $limit = 200): array
    {
        return $this->fetchRecentFiltered($limit, '', '', '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRecentFiltered(
        int $limit,
        string $source,
        string $status,
        string $eventName,
        string $attributionSource = '',
        string $touchType = ''
    ): array {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $select = $conn->select()
            ->from($table)
            ->order('entity_id DESC')
            ->limit((int) $limit);
        $source = trim($source);
        if ($source !== '') {
            $select->where('source = ?', $source);
        }
        $status = trim($status);
        if ($status !== '') {
            $select->where('status = ?', $status);
        }
        $eventName = trim($eventName);
        if ($eventName !== '') {
            $select->where('event_name LIKE ?', '%' . $eventName . '%');
        }
        $rows = $conn->fetchAll($select);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $payload = [];
            if (!empty($row['payload_json'])) {
                $decoded = json_decode((string) $row['payload_json'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
            $row['debug_context'] = $this->debugAttributionContext->buildContext($payload);
            if (!$this->debugAttributionContext->rowMatchesFilters($row, $attributionSource, $touchType)) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }

    public function clear(): void
    {
        $conn = $this->resource->getConnection();
        $conn->truncateTable($this->resource->getTableName(self::TABLE));
    }
}
