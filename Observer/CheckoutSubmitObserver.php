<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Sales\Api\Data\OrderInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\HashedPiiEnricher;
use Panto\Source\Model\OrderLineSerializer;

class CheckoutSubmitObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly OrderLineSerializer $orderLineSerializer,
        private readonly HashedPiiEnricher $hashedPiiEnricher
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface|null $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getEntityId()) {
            return;
        }

        $data = [
            'order_id' => (int) $order->getEntityId(),
            'order_increment_id' => (string) $order->getIncrementId(),
            'status' => (string) $order->getStatus(),
            'total' => (string) $order->getGrandTotal(),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'customer_id' => (int) $order->getCustomerId(),
            'items' => $this->orderLineSerializer->getItems($order),
            'order_totals' => $this->orderLineSerializer->orderTotals($order),
            'billing_email' => (string) $order->getCustomerEmail(),
            'occurrence' => 'order_created',
            'context' => StorefrontContext::fromRequest($this->request, 'checkout', 'Order placed'),
        ];
        $data = $this->hashedPiiEnricher->enrich($order, $data, (int) $order->getStoreId());

        $this->dispatcher->dispatch('magento_checkout_order_placed', $data);
    }
}
