<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Quote-level tax / shipping / discount for commerce hoist on pre-purchase events.
 */
class QuoteOrderTotals
{
    /**
     * @return array{tax_total: float, shipping_total: float, discount_total: float}
     */
    public static function fromQuote(mixed $quote): array
    {
        $totals = [
            'tax_total' => 0.0,
            'shipping_total' => 0.0,
            'discount_total' => 0.0,
        ];
        if (!is_object($quote)) {
            return $totals;
        }

        try {
            $addr = method_exists($quote, 'getShippingAddress') ? $quote->getShippingAddress() : null;
            if (is_object($addr)) {
                if (method_exists($addr, 'getTaxAmount')) {
                    $totals['tax_total'] = abs((float) $addr->getTaxAmount());
                }
                if (method_exists($addr, 'getShippingAmount')) {
                    $totals['shipping_total'] = abs((float) $addr->getShippingAmount());
                }
                if (method_exists($addr, 'getDiscountAmount')) {
                    $totals['discount_total'] = abs((float) $addr->getDiscountAmount());
                }
            }
        } catch (\Throwable) {
            return $totals;
        }

        return $totals;
    }
}
