<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * Maps a Magento order's state and paid/refunded totals to the canonical commerce.payment_status.
 * Reads raw order data keys so the same logic works for current values and getOrigData().
 */
class PaymentStatusNormalizer
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';
    public const PARTIALLY_REFUNDED = 'partially_refunded';

    public const EVENT_NAME = 'magento_order_payment_status_changed';

    private const STATE_CANCELED = 'canceled';
    private const STATE_CLOSED = 'closed';

    public static function fromValues(string $state, float $totalPaid, float $grandTotal, float $totalRefunded): string
    {
        if ($state === self::STATE_CANCELED) {
            return self::CANCELLED;
        }
        if ($state === self::STATE_CLOSED) {
            return self::REFUNDED;
        }
        if ($totalRefunded > 0) {
            return ($grandTotal > 0 && $totalRefunded >= $grandTotal) ? self::REFUNDED : self::PARTIALLY_REFUNDED;
        }
        $totalDue = $grandTotal - $totalPaid;
        if ($totalPaid > 0 && $totalDue <= 0) {
            return self::PAID;
        }
        return self::PENDING;
    }

    /** Current payment status of the order. */
    public static function fromOrder(object $order): string
    {
        return self::fromValues(
            (string) $order->getData('state'),
            (float) $order->getData('total_paid'),
            (float) $order->getData('grand_total'),
            (float) $order->getData('total_refunded')
        );
    }

    /** Payment status before the current save, or null for a new order (no original data). */
    public static function fromOrigData(object $order): ?string
    {
        $state = $order->getOrigData('state');
        if ($state === null) {
            return null;
        }
        return self::fromValues(
            (string) $state,
            (float) $order->getOrigData('total_paid'),
            (float) $order->getOrigData('grand_total'),
            (float) $order->getOrigData('total_refunded')
        );
    }
}
