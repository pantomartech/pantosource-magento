<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CartRemoveObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function execute(Observer $observer): void
    {
        // Guard: only fire for explicit user-initiated cart remove actions.
        //
        // sales_quote_remove_item fires for ALL quote item removals, including:
        //   • post-purchase cart clearing during order processing
        //   • admin order creation
        //   • programmatic quote manipulation via plugins or REST API
        //
        // User-initiated removes always arrive via one of these known controller actions.
        // Any other caller (checkout submit, order management) will not match and is suppressed.
        $allowedActions = [
            'checkout_cart_delete',         // Classic cart page remove (GET ?remove_item=)
            'checkout_cart_ajaxdelete',     // Ajax cart remove (some themes / mini-cart)
            'checkout_sidebar_removeItem',  // Mini-cart sidebar remove
            'checkout_cart_updatePost',     // Qty set to 0 via cart update form
        ];
        if (!in_array($this->request->getFullActionName(), $allowedActions, true)) {
            return;
        }

        $item = $observer->getEvent()->getQuoteItem();
        if (!$item) {
            return;
        }

        $qty = (float) $item->getQty();
        $unitPrice = $qty > 0 ? (float) $item->getRowTotal() / $qty : (float) $item->getPrice();
        $title = (string) $item->getName();
        $sku = (string) $item->getSku();
        $productId = (int) $item->getProductId();

        try {
            $currency = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = '';
        }

        $category = '';
        try {
            $product = $item->getProduct();
            if ($product !== null) {
                $catIds = $product->getCategoryIds();
                if (!empty($catIds)) {
                    $category = (string) ($this->categoryRepository->get((int) $catIds[0])->getName() ?? '');
                }
            }
        } catch (\Throwable) {
            $category = '';
        }

        $cartId = 0;
        try {
            $quote = $item->getQuote();
            if ($quote && $quote->getId()) {
                $cartId = (int) $quote->getId();
            } elseif ($item->getQuoteId()) {
                $cartId = (int) $item->getQuoteId();
            }
        } catch (\Throwable) {
            $cartId = (int) $item->getQuoteId();
        }

        $line = [
            'product_id' => $productId,
            'title' => $title,
            'sku' => $sku,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'category' => $category,
        ];

        $data = [
            'quote_item_id' => (int) $item->getId(),
            'product_id' => $productId,
            'quantity' => (int) $qty,
            'sku' => $sku,
            'title' => $title,
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'category' => $category,
            'items' => [$line],
            'context' => StorefrontContext::fromRequest($this->request, 'cart', ''),
        ];
        if ($cartId > 0) {
            $data['cart_id'] = $cartId;
        }
        $this->dispatcher->dispatch('magento_remove_from_cart', $data);
    }
}
