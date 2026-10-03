<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\ResourceConnection;

class OrderSnapshotTombstoneRepository
{
    private const TABLE = 'pantosource_snapshot_tombstone';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function queue(int $orderId, int $storeId): void
    {
        if ($orderId <= 0) {
            return;
        }
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $conn->insertOnDuplicate($table, [
            'order_id' => $orderId,
            'store_id' => $storeId,
            'tombstone_at' => gmdate('Y-m-d H:i:s'),
        ], ['store_id', 'tombstone_at']);
    }

    /**
     * @return list<array{order_id: int, store_id: int, tombstone_at: string}>
     */
    public function drainForStore(int $storeId, int $limit = 500): array
    {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $rows = $conn->fetchAll(
            $conn->select()
                ->from($table, ['order_id', 'store_id', 'tombstone_at'])
                ->where('store_id = ?', $storeId)
                ->order('order_id ASC')
                ->limit($limit)
        );
        if (!is_array($rows)) {
            return [];
        }

        /** @var list<array{order_id: int, store_id: int, tombstone_at: string}> $out */
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'order_id' => (int) $row['order_id'],
                'store_id' => (int) $row['store_id'],
                'tombstone_at' => (string) $row['tombstone_at'],
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $orderIds
     */
    public function deleteByOrderIds(array $orderIds): void
    {
        $orderIds = array_values(array_filter(array_map('intval', $orderIds), static fn (int $id): bool => $id > 0));
        if ($orderIds === []) {
            return;
        }
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $conn->delete($table, ['order_id IN (?)' => $orderIds]);
    }
}
