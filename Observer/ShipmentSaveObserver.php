<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class ShipmentSaveObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request
    ) {
    }

    public function execute(Observer $observer): void
    {
        $shipment = $observer->getEvent()->getShipment();
        if (!$shipment || !$shipment->getOrderId()) {
            return;
        }
        $data = [
            'order_id' => (int) $shipment->getOrderId(),
            'shipment_id' => (int) $shipment->getEntityId(),
            'context' => StorefrontContext::fromRequest($this->request, 'admin', 'Shipment'),
        ];
        $this->dispatcher->dispatch('magento_order_fulfilled', $data);
    }
}
