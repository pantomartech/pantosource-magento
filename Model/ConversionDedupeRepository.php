<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\ResourceConnection;

class ConversionDedupeRepository
{
    private const TABLE = 'pantosource_order_dedupe';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function isPurchaseEmitted(int $orderId): bool
    {
        $conn = $this->resource->getConnection();
        $t = $this->resource->getTableName(self::TABLE);
        $v = $conn->fetchOne("SELECT purchase_emitted FROM {$t} WHERE order_id = ?", [$orderId]);
        return (int) $v === 1;
    }

    public function markPurchaseEmitted(int $orderId): void
    {
        $conn = $this->resource->getConnection();
        $t = $this->resource->getTableName(self::TABLE);
        $sql = "INSERT INTO {$t} (order_id, purchase_emitted, updated_at) VALUES (?, 1, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE purchase_emitted = 1, updated_at = UTC_TIMESTAMP()";
        $conn->query($sql, [$orderId]);
    }
}
