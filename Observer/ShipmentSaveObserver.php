<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Sales\Api\OrderRepositoryInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\CheckoutIdentityRepository;
use Panto\Source\Model\ConversionDedupeRepository;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\OrderLineSerializer;

class ShipmentSaveObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly ConversionDedupeRepository $dedupeRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderLineSerializer $orderLineSerializer,
        private readonly CheckoutIdentityRepository $checkoutIdentity
    ) {
    }

    public function execute(Observer $observer): void
    {
        $shipment = $observer->getEvent()->getShipment();
        if (!$shipment || !$shipment->getOrderId()) {
            return;
        }
        $orderId = (int) $shipment->getOrderId();
        if (!$this->dedupeRepository->isPurchaseEmitted($orderId)) {
            return;
        }
        if ($this->dedupeRepository->isFulfilledEmitted($orderId)) {
            return;
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Throwable) {
            return;
        }

        $data = [
            'order_id' => $orderId,
            'shipment_id' => (int) $shipment->getEntityId(),
            'total' => (string) $order->getGrandTotal(),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'items' => $this->orderLineSerializer->getItems($order),
            'order_totals' => $this->orderLineSerializer->orderTotals($order),
            'context' => StorefrontContext::fromRequest($this->request, 'admin', 'Shipment'),
        ];
        $data = $this->checkoutIdentity->resolveIdentityFromOrder($data, $orderId);

        $ok = $this->dispatcher->dispatch('magento_order_fulfilled', $data);
        if ($ok) {
            $this->dedupeRepository->markFulfilledEmitted($orderId);
        }
    }
}
