<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * Magento-free commerce / product field mapping so PayloadBuilder and PHPUnit share one implementation.
 */
class CommerceFields
{
    /**
     * Prefer order_totals.payment_method (orders); fall back to data.payment_method (add_payment_info).
     *
     * @param array<string, mixed> $data
     */
    public static function resolvePaymentMethod(array $data): string
    {
        $ot = $data['order_totals'] ?? null;
        if (is_array($ot) && isset($ot['payment_method']) && is_string($ot['payment_method'])) {
            $fromTotals = trim($ot['payment_method']);
            if ($fromTotals !== '') {
                return $fromTotals;
            }
        }
        if (isset($data['payment_method']) && is_string($data['payment_method'])) {
            return trim($data['payment_method']);
        }
        return '';
    }

    /**
     * Hoist catalog fields from event data onto the canonical product block.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function hoistProduct(array $data): array
    {
        $product = [];
        if (isset($data['product_id']) && $data['product_id'] !== '' && $data['product_id'] !== null) {
            $product['product_id'] = (string) $data['product_id'];
        }
        if (isset($data['variation_id']) && $data['variation_id'] !== '' && $data['variation_id'] !== null) {
            $product['variation_id'] = (string) $data['variation_id'];
        }
        $name = '';
        if (isset($data['product_name'])) {
            $name = (string) $data['product_name'];
        } elseif (isset($data['name'])) {
            $name = (string) $data['name'];
        } elseif (isset($data['title'])) {
            $name = (string) $data['title'];
        }
        if ($name !== '') {
            $product['name'] = $name;
        }
        if (isset($data['sku']) && (string) $data['sku'] !== '') {
            $product['sku'] = (string) $data['sku'];
        }
        if (isset($data['price']) && $data['price'] !== '' && is_numeric($data['price'])) {
            $product['price'] = (float) $data['price'];
        } elseif (isset($data['unit_price']) && is_numeric($data['unit_price'])) {
            $product['price'] = (float) $data['unit_price'];
        }
        return $product;
    }
}
