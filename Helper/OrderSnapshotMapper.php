<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Maps Magento order fields to portal store-health snapshot rows (no PII).
 */
final class OrderSnapshotMapper
{
    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public static function toSnapshot(array $order, ?string $salesChannel, bool $purchaseEventSent): array
    {
        [$channel, $isOnline] = OrderPlacementChannel::snapshotChannel($salesChannel);
        $createdAt = self::isoUtc((string) ($order['created_at'] ?? ''));
        $updatedAt = self::isoUtc((string) ($order['updated_at'] ?? ''));
        $status = (string) ($order['status'] ?? '');
        $state = (string) ($order['state'] ?? '');
        $cancelledAt = null;
        if ($status === 'canceled' || $state === 'canceled') {
            $cancelledAt = $updatedAt !== '' ? $updatedAt : $createdAt;
        }

        return [
            'order_id' => (string) ($order['entity_id'] ?? $order['order_id'] ?? ''),
            'order_number' => isset($order['increment_id']) ? (string) $order['increment_id'] : null,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'cancelled_at' => $cancelledAt,
            'purchased_at' => $createdAt !== '' ? $createdAt : null,
            'purchase_event_sent' => $purchaseEventSent,
            'status' => $status !== '' ? $status : $state,
            'financial_status' => $state !== '' ? $state : null,
            'channel' => $channel,
            'is_online_store' => $isOnline,
            'is_test' => false,
            'currency' => (string) ($order['order_currency_code'] ?? $order['currency'] ?? ''),
            'total' => self::decimal($order['grand_total'] ?? 0),
            'subtotal' => self::decimal($order['subtotal'] ?? 0),
            'tax' => self::decimal($order['tax_amount'] ?? 0),
            'shipping' => self::decimal($order['shipping_amount'] ?? 0),
            'discount' => self::decimal(abs((float) ($order['discount_amount'] ?? 0))),
            'refunded_total' => self::decimal($order['total_refunded'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function tombstone(int $orderId, string $updatedAt): array
    {
        return [
            'order_id' => (string) $orderId,
            'updated_at' => self::isoUtc($updatedAt),
            'deleted' => true,
        ];
    }

    private static function decimal(mixed $value): string
    {
        $n = is_numeric($value) ? (float) $value : 0.0;

        return number_format($n, 4, '.', '');
    }

    private static function isoUtc(string $mysqlDatetime): string
    {
        $mysqlDatetime = trim($mysqlDatetime);
        if ($mysqlDatetime === '') {
            return gmdate('Y-m-d\TH:i:s\Z');
        }
        $ts = strtotime($mysqlDatetime . ' UTC');
        if ($ts === false) {
            return gmdate('Y-m-d\TH:i:s\Z');
        }

        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }
}
