<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\CheckoutInitSessionKey;
use Panto\Source\Helper\QuoteLineIds;
use Panto\Source\Helper\QuoteOrderTotals;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\CheckoutIdentityRepository;
use Panto\Source\Model\EventDispatcher;

class CheckoutInitObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly CheckoutSession $checkoutSession,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CheckoutIdentityRepository $checkoutIdentity
    ) {
    }

    public function execute(Observer $observer): void
    {
        unset($observer);
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
        }
        $sessionKey = CheckoutInitSessionKey::forQuote((int) $quote->getId());
        if ($this->checkoutSession->getData($sessionKey)) {
            return;
        }
        $this->checkoutSession->setData($sessionKey, 1);

        $items = [];
        /** @var array<int, string> $categoryCache */
        $categoryCache = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $productId = (int) $item->getProductId();
            $parentItem = $item->getParentItem();
            $parentId = $parentItem ? (int) $parentItem->getProductId() : $productId;
            $variationId = QuoteLineIds::variantId($item, $productId);
            if ($variationId <= 0 && $parentItem) {
                $variationId = $productId;
            }

            // Use row total divided by quantity for post-coupon unit price; fall back to catalog price.
            $qty = (float) $item->getQty();
            $unitPrice = $qty > 0 ? (float) $item->getRowTotal() / $qty : (float) $item->getPrice();

            // Resolve first product category; cache by category ID to avoid repeated repo calls.
            $category = '';
            try {
                $product = $item->getProduct();
                if ($product !== null) {
                    $catIds = $product->getCategoryIds();
                    if (!empty($catIds)) {
                        $catId = (int) $catIds[0];
                        if (!isset($categoryCache[$catId])) {
                            $categoryCache[$catId] = (string) ($this->categoryRepository->get($catId)->getName() ?? '');
                        }
                        $category = $categoryCache[$catId];
                    }
                }
            } catch (\Throwable) {
                $category = '';
            }

            $items[] = [
                'product_id' => $productId,
                'parent_id'  => $parentId,
                'variation_id' => $variationId,
                'title'      => $item->getName(),
                'sku'        => $item->getSku(),
                'category'   => $category,
                'quantity'   => $qty,
                'unit_price' => $unitPrice,
            ];
        }

        $data = [
            'cart_id' => (int) $quote->getId(),
            'cart_total' => (string) $quote->getGrandTotal(),
            'currency' => (string) $quote->getQuoteCurrencyCode(),
            'item_count' => (int) $quote->getItemsCount(),
            'items' => $items,
            'order_totals' => QuoteOrderTotals::fromQuote($quote),
            'context' => StorefrontContext::fromRequest($this->request, 'checkout', 'Checkout'),
        ];
        $email = $this->quoteEmail($quote);
        if ($email !== '') {
            $data['billing_email'] = $email;
        }
        $this->checkoutIdentity->persistToCheckoutSession($data);
        $this->dispatcher->dispatch('checkout_initiated', $data);
        $this->maybeDispatchCheckoutEmailIdentify($email);
    }

    private function quoteEmail(mixed $quote): string
    {
        try {
            $billing = method_exists($quote, 'getBillingAddress') ? $quote->getBillingAddress() : null;
            $email = '';
            if ($billing && method_exists($billing, 'getEmail')) {
                $email = trim((string) $billing->getEmail());
            }
            if ($email === '' && method_exists($quote, 'getCustomerEmail')) {
                $email = trim((string) $quote->getCustomerEmail());
            }
            return $email;
        } catch (\Throwable) {
            return '';
        }
    }

    private function maybeDispatchCheckoutEmailIdentify(string $email): void
    {
        if ($email === '') {
            return;
        }
        if ($this->checkoutSession->getData(PaymentInfoObserver::IDENTIFY_SESSION_KEY)) {
            return;
        }
        $this->checkoutSession->setData(PaymentInfoObserver::IDENTIFY_SESSION_KEY, 1);
        $this->dispatcher->dispatch('magento_customer_identified', [
            'email' => $email,
            'billing_email' => $email,
            'identity_link' => true,
            'identity_trigger' => 'checkout_email',
        ]);
    }
}
