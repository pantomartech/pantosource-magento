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
            $items[] = [
                'product_id' => (int) $item->getProductId(),
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
