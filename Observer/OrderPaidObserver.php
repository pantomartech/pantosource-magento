<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Panto\Source\Model\PurchaseConversionEmitter;

/**
 * Fallback conversion when the thank-you page never runs (offsite gateways).
 * Dedupe is shared with CheckoutSuccessObserver via ConversionDedupeRepository.
 */
class OrderPaidObserver implements ObserverInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PurchaseConversionEmitter $purchaseEmitter
    ) {
    }

    public function execute(Observer $observer): void
    {
        $invoice = $observer->getEvent()->getInvoice();
        $orderId = 0;
        if ($invoice && method_exists($invoice, 'getOrderId')) {
            $orderId = (int) $invoice->getOrderId();
        }
        if ($orderId <= 0) {
            $order = $observer->getEvent()->getOrder();
            if ($order && method_exists($order, 'getEntityId')) {
                $orderId = (int) $order->getEntityId();
            }
        }
        if ($orderId <= 0) {
            return;
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Throwable) {
            return;
        }

        $this->purchaseEmitter->emit($order, 'subsequent', 'Invoice paid');
    }
}
