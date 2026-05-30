<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CartRemoveObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ConfigInterface $config
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

        // Mirror CartAddObserver: suppress PHP-side emission when the frontend
        // RequireJS tracker is active for this store, to avoid double-counting
        // if a theme-level JS tracker also handles remove events.
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            $storeId = 0;
        }
        if ($this->config->isFrontendTrackerEnabled($storeId)) {
            return;
        }

        $item = $observer->getEvent()->getQuoteItem();
        if (!$item) {
            return;
        }

        // Resolve unit price from row total / qty; fall back to catalog price.
        $qty = (float) $item->getQty();
        $unitPrice = $qty > 0 ? (float) $item->getRowTotal() / $qty : (float) $item->getPrice();

        // Resolve currency.
        try {
            $currency = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = '';
        }

        // Resolve first product category.
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

        $data = [
            'quote_item_id' => (int) $item->getId(),
            'product_id'    => (int) $item->getProductId(),
            'quantity'      => (int) $item->getQty(),
            'sku'           => (string) $item->getSku(),
            'unit_price'    => $unitPrice,
            'currency'      => $currency,
            'category'      => $category,
            'context'       => StorefrontContext::fromRequest($this->request, 'cart', ''),
        ];
        $this->dispatcher->dispatch('magento_remove_from_cart', $data);
    }
}
