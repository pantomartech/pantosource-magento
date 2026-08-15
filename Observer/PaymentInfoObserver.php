<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\QuoteCommerceSnapshot;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

/**
 * Observer for sales_order_payment_place_end — fires after payment info is saved,
 * before the order is submitted. Emits add_payment_info for GA4 / TikTok tracking.
 */
class PaymentInfoObserver implements ObserverInterface
{
    public const SESSION_KEY = 'pantosource_payment_info_sent';

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->checkoutSession->getData(self::SESSION_KEY)) {
            return;
        }
        $payment = $observer->getEvent()->getPayment();
        if (!$payment) {
            return;
        }
        $method = '';
        try {
            $method = (string) $payment->getMethod();
        } catch (\Throwable) {
        }

        $snapshot = ['cart_total' => '', 'currency' => '', 'items' => []];
        try {
            $snapshot = QuoteCommerceSnapshot::fromQuote($this->checkoutSession->getQuote());
        } catch (\Throwable) {
        }

        $data = [
            'payment_method' => $method,
            'cart_total'     => $snapshot['cart_total'],
            'currency'       => $snapshot['currency'],
            'items'          => $snapshot['items'],
            'context'        => StorefrontContext::fromRequest($this->request, 'checkout', 'Checkout'),
        ];
        $this->checkoutSession->setData(self::SESSION_KEY, 1);
        $this->dispatcher->dispatch('add_payment_info', $data);
    }
}
