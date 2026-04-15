<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CheckoutInitObserver implements ObserverInterface
{
    private const SESSION_KEY = 'pantosource_checkout_init_sent';

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function execute(Observer $observer): void
    {
        unset($observer);
        if ($this->checkoutSession->getData(self::SESSION_KEY)) {
            return;
        }
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
        }
        $this->checkoutSession->setData(self::SESSION_KEY, 1);

        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'product_id' => (int) $item->getProductId(),
                'title' => $item->getName(),
                'quantity' => (float) $item->getQty(),
                'unit_price' => (float) $item->getPrice(),
            ];
        }

        $data = [
            'cart_total' => (string) $quote->getGrandTotal(),
            'currency' => (string) $quote->getQuoteCurrencyCode(),
            'item_count' => (int) $quote->getItemsCount(),
            'items' => $items,
            'context' => StorefrontContext::fromRequest($this->request, 'checkout', 'Checkout'),
        ];
        $this->dispatcher->dispatch('checkout_initiated', $data);
    }
}
