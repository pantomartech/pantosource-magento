<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
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

        $cartTotal = '';
        $currency = '';
        $items = [];
        try {
            $quote = $this->checkoutSession->getQuote();
            if ($quote && $quote->getId()) {
                $cartTotal = (string) $quote->getGrandTotal();
                $currency = (string) $quote->getQuoteCurrencyCode();
                foreach ($quote->getAllVisibleItems() as $item) {
                    $qty = (float) $item->getQty();
                    $unitPrice = $qty > 0 ? (float) $item->getRowTotal() / $qty : (float) $item->getPrice();
                    $items[] = [
                        'product_id' => (int) $item->getProductId(),
                        'title'      => $item->getName(),
                        'sku'        => $item->getSku(),
                        'quantity'   => $qty,
                        'unit_price' => $unitPrice,
                    ];
                }
            }
        } catch (\Throwable) {
        }

        $data = [
            'payment_method' => $method,
            'cart_total'     => $cartTotal,
            'currency'       => $currency,
            'items'          => $items,
            'context'        => StorefrontContext::fromRequest($this->request, 'checkout', 'Checkout'),
        ];
        $this->checkoutSession->setData(self::SESSION_KEY, 1);
        $this->dispatcher->dispatch('add_payment_info', $data);
    }
}
