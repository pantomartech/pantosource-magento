<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\QuoteCommerceSnapshot;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\CustomerProfileBuilder;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\HashedPiiEnricher;

/**
 * Observer for sales_order_payment_place_end — fires after payment info is saved,
 * before the order is submitted. Emits add_payment_info for GA4 / TikTok tracking.
 */
class PaymentInfoObserver implements ObserverInterface
{
    public const SESSION_KEY = 'pantosource_payment_info_sent';
    public const IDENTIFY_SESSION_KEY = 'pantosource_checkout_email_identify_sent';

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerProfileBuilder $customerProfileBuilder
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

        $snapshot = ['cart_total' => '', 'currency' => '', 'items' => [], 'order_totals' => ['tax_total' => 0.0, 'shipping_total' => 0.0, 'discount_total' => 0.0]];
        try {
            $snapshot = QuoteCommerceSnapshot::fromQuote($this->checkoutSession->getQuote());
        } catch (\Throwable) {
        }

        $data = [
            'payment_method' => $method,
            'cart_total'     => $snapshot['cart_total'],
            'currency'       => $snapshot['currency'],
            'items'          => $snapshot['items'],
            'order_totals'   => $snapshot['order_totals'],
            'context'        => StorefrontContext::fromRequest($this->request, 'checkout', 'Checkout'),
        ];
        try {
            $quote = $this->checkoutSession->getQuote();
            $billing = $quote && method_exists($quote, 'getBillingAddress') ? $quote->getBillingAddress() : null;
            $shipping = $quote && method_exists($quote, 'getShippingAddress') ? $quote->getShippingAddress() : null;
            $profile = $this->customerProfileBuilder->profileFromAddressObjects($billing, $shipping);
            if ($profile !== []) {
                $data['customer_profile'] = $profile;
            }
            $email = '';
            if ($billing && method_exists($billing, 'getEmail')) {
                $email = trim((string) $billing->getEmail());
            }
            if ($email === '' && $quote && method_exists($quote, 'getCustomerEmail')) {
                $email = trim((string) $quote->getCustomerEmail());
            }
            if ($email !== '') {
                $data['billing_email'] = $email;
            }
        } catch (\Throwable) {
        }
        $this->checkoutSession->setData(self::SESSION_KEY, 1);
        $this->maybeDispatchCheckoutEmailIdentify($data);
        $this->dispatcher->dispatch('add_payment_info', $data);
    }

    /**
     * Emit identify once when quote billing email is present (add_payment_info stays track).
     *
     * @param array<string, mixed> $data
     */
    public function maybeDispatchCheckoutEmailIdentify(array $data): void
    {
        $email = trim((string) ($data['billing_email'] ?? $data['email'] ?? ''));
        if ($email === '') {
            return;
        }
        if ($this->checkoutSession->getData(self::IDENTIFY_SESSION_KEY)) {
            return;
        }
        $this->checkoutSession->setData(self::IDENTIFY_SESSION_KEY, 1);
        $identify = [
            'email' => $email,
            'billing_email' => $email,
            'identity_link' => true,
            'identity_trigger' => 'checkout_email',
        ];
        if (!empty($data['customer_id'])) {
            $identify['customer_id'] = $data['customer_id'];
        }
        if (!empty($data['customer_profile']) && is_array($data['customer_profile'])) {
            $identify['customer_profile'] = $data['customer_profile'];
        }
        $this->dispatcher->dispatch('magento_customer_identified', $identify);
    }
}
