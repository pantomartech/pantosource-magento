<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\ResourceConnection;

class ConversionDedupeRepository
{
    private const TABLE = 'pantosource_order_dedupe';
    private const PAYMENT_STATUS_TABLE = 'pantosource_order_payment_status';

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
        $sql = "INSERT INTO {$t} (order_id, purchase_emitted, fulfilled_emitted, updated_at) VALUES (?, 1, 0, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE purchase_emitted = 1, updated_at = UTC_TIMESTAMP()";
        $conn->query($sql, [$orderId]);
    }

    public function isFulfilledEmitted(int $orderId): bool
    {
        $conn = $this->resource->getConnection();
        $t = $this->resource->getTableName(self::TABLE);
        $v = $conn->fetchOne("SELECT fulfilled_emitted FROM {$t} WHERE order_id = ?", [$orderId]);
        return (int) $v === 1;
    }

    public function markFulfilledEmitted(int $orderId): void
    {
        $conn = $this->resource->getConnection();
        $t = $this->resource->getTableName(self::TABLE);
        $sql = "INSERT INTO {$t} (order_id, purchase_emitted, fulfilled_emitted, updated_at) VALUES (?, 0, 1, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE fulfilled_emitted = 1, updated_at = UTC_TIMESTAMP()";
        $conn->query($sql, [$orderId]);
    }

    /** True when this order already sent (or carried on its purchase) this payment status. */
    public function hasEmitted(int $orderId, string $paymentStatus): bool
    {
        $conn = $this->resource->getConnection();
        $t = $this->resource->getTableName(self::PAYMENT_STATUS_TABLE);
        $v = $conn->fetchOne(
            "SELECT 1 FROM {$t} WHERE order_id = ? AND payment_status = ?",
            [$orderId, $paymentStatus]
        );
        return (int) $v === 1;
    }

    public function markEmitted(int $orderId, string $paymentStatus): void
    {
        $conn = $this->resource->getConnection();
        $t = $this->resource->getTableName(self::PAYMENT_STATUS_TABLE);
        $sql = "INSERT IGNORE INTO {$t} (order_id, payment_status, emitted_at) VALUES (?, ?, UTC_TIMESTAMP())";
        $conn->query($sql, [$orderId, $paymentStatus]);
    }
}
