<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Quote lines/totals for add_payment_info (server observer and tracker mixin enrich).
 */
class QuoteCommerceSnapshot
{
    /**
     * @return array{
     *   cart_total: string,
     *   currency: string,
     *   items: list<array<string, mixed>>,
     *   order_totals: array{tax_total: float, shipping_total: float, discount_total: float}
     * }
     */
    public static function fromQuote(mixed $quote): array
    {
        $empty = [
            'cart_total' => '',
            'currency' => '',
            'items' => [],
            'order_totals' => QuoteOrderTotals::fromQuote(null),
        ];
        if (!is_object($quote) || !method_exists($quote, 'getId') || !$quote->getId()) {
            return $empty;
        }

        $items = [];
        try {
            $visible = method_exists($quote, 'getAllVisibleItems') ? $quote->getAllVisibleItems() : [];
            foreach ($visible as $item) {
                if (!is_object($item)) {
                    continue;
                }
                $productId = method_exists($item, 'getProductId') ? (int) $item->getProductId() : 0;
                $qty = method_exists($item, 'getQty') ? (float) $item->getQty() : 0.0;
                $rowTotal = method_exists($item, 'getRowTotal') ? (float) $item->getRowTotal() : 0.0;
                $price = method_exists($item, 'getPrice') ? (float) $item->getPrice() : 0.0;
                $unitPrice = $qty > 0 && $rowTotal > 0 ? $rowTotal / $qty : $price;
                $parentId = $productId;
                if (is_callable([$item, 'getParentItem'])) {
                    try {
                        $parent = $item->getParentItem();
                        if (is_object($parent)) {
                            $resolvedParent = QuoteItemFields::int($parent, 'product_id', 'getProductId');
                            if ($resolvedParent > 0) {
                                $parentId = $resolvedParent;
                            }
                        }
                    } catch (\Throwable) {
                    }
                }
                $line = [
                    'product_id' => $productId,
                    'parent_id' => $parentId,
                    'title' => method_exists($item, 'getName') ? (string) $item->getName() : '',
                    'sku' => method_exists($item, 'getSku') ? (string) $item->getSku() : '',
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                ];
                $variationId = QuoteLineIds::variantId($item, $productId);
                if ($variationId > 0) {
                    $line['variation_id'] = $variationId;
                }
                $items[] = $line;
            }
        } catch (\Throwable) {
            $items = [];
        }

        $cartTotal = '';
        $currency = '';
        try {
            if (method_exists($quote, 'getGrandTotal')) {
                $cartTotal = (string) $quote->getGrandTotal();
            }
            if (method_exists($quote, 'getQuoteCurrencyCode')) {
                $currency = (string) $quote->getQuoteCurrencyCode();
            }
        } catch (\Throwable) {
        }

        return [
            'cart_total' => $cartTotal,
            'currency' => $currency,
            'items' => $items,
            'order_totals' => QuoteOrderTotals::fromQuote($quote),
        ];
    }
}
