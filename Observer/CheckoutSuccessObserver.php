<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Panto\Source\Model\PurchaseConversionEmitter;

class CheckoutSuccessObserver implements ObserverInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PurchaseConversionEmitter $purchaseEmitter
    ) {
    }

    public function execute(Observer $observer): void
    {
        $orderIds = $observer->getEvent()->getOrderIds();
        $orderId = $this->resolveOrderId($orderIds);
        if ($orderId <= 0) {
            return;
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Throwable) {
            return;
        }

        $this->purchaseEmitter->emit($order, 'first', 'Thank you');
    }

    /**
     * @param mixed $orderIds
     */
    private function resolveOrderId(mixed $orderIds): int
    {
        if (is_array($orderIds) && $orderIds !== []) {
            $vals = array_values($orderIds);
            return (int) ($vals[0] ?? 0);
        }
        return 0;
    }
}
