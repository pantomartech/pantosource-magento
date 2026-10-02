<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\ConversionDedupeRepository;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\PaymentStatusNormalizer;

/**
 * On sales_order_save_after, emits magento_order_payment_status_changed once per distinct
 * normalized payment status, and only after the purchase for the order was sent.
 */
class OrderPaymentStatusObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly ConversionDedupeRepository $dedupeRepository
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }
        $orderId = (int) $order->getId();
        if ($orderId <= 0 || !$this->dedupeRepository->isPurchaseEmitted($orderId)) {
            return;
        }

        $current = PaymentStatusNormalizer::fromOrder($order);
        $previous = PaymentStatusNormalizer::fromOrigData($order);
        if ($previous === $current || $this->dedupeRepository->hasEmitted($orderId, $current)) {
            return;
        }

        $data = [
            'order_id' => $orderId,
            'status' => (string) $order->getData('state'),
            'payment_status' => $current,
            'previous_payment_status' => $previous,
            'total' => (string) $order->getGrandTotal(),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'occurrence' => $current,
            'context' => StorefrontContext::fromRequest($this->request, '', ''),
        ];
        if ($this->dispatcher->dispatch(PaymentStatusNormalizer::EVENT_NAME, $data, 'track')) {
            $this->dedupeRepository->markEmitted($orderId, $current);
        }
    }
}
