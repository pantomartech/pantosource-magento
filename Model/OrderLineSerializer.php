<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Sales\Api\Data\OrderInterface;

class OrderLineSerializer
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getItems(OrderInterface $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $productId = (int) $item->getProductId();
            $parentItem = $item->getParentItem();
            $parentId = $parentItem ? (int) $parentItem->getProductId() : $productId;
            $variationId = $parentItem ? $productId : 0;

            $items[] = [
                'product_id' => $productId,
                'parent_id' => $parentId,
                'variation_id' => $variationId,
                'title' => $item->getName(),
                'sku' => $item->getSku(),
                'quantity' => (float) $item->getQtyOrdered(),
                'unit_price' => (float) $item->getPrice(),
                'currency' => $order->getOrderCurrencyCode(),
                'total' => (float) $item->getRowTotal(),
            ];
        }
        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function orderTotals(OrderInterface $order): array
    {
        return [
            'subtotal' => (string) $order->getSubtotal(),
            'total' => (string) $order->getGrandTotal(),
            'discount_total' => (string) $order->getDiscountAmount(),
            'shipping_total' => (string) $order->getShippingAmount(),
            'tax_total' => (string) $order->getTaxAmount(),
            'payment_method' => ($order->getPayment() !== null) ? (string) $order->getPayment()->getMethod() : '',
        ];
    }
}
