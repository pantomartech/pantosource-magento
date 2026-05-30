<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\OrderLineSerializer;

/**
 * Fires when an order status history entry is saved.
 * Detects transitions to 'canceled' state and emits magento_order_cancelled.
 */
class OrderCancelObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly OrderLineSerializer $orderLineSerializer
    ) {
    }

    public function execute(Observer $observer): void
    {
        $historyItem = $observer->getEvent()->getStatusHistory();
        if (!$historyItem) {
            return;
        }

        $order = $historyItem->getOrder();
        if (!$order) {
            return;
        }

        // Only emit once when the order status is 'canceled'.
        if ((string) $order->getStatus() !== 'canceled') {
            return;
        }

        // Avoid duplicate events: use a flag on the order.
        $flagKey = 'pantosource_cancel_emitted';
        if ($order->getData($flagKey)) {
            return;
        }
        $order->setData($flagKey, 1);

        $data = [
            'order_id' => (int) $order->getId(),
            'total'    => (string) $order->getGrandTotal(),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'items'    => $this->orderLineSerializer->getItems($order),
            'context'  => StorefrontContext::fromRequest($this->request, '', ''),
        ];
        $this->dispatcher->dispatch('magento_order_cancelled', $data);
    }
}
