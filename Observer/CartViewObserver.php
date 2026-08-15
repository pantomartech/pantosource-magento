<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

/**
 * Fires when the cart page is displayed (controller_action_postdispatch_checkout_cart_index).
 * Emits the view_cart event with full line-item detail.
 */
class CartViewObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly CheckoutSession $checkoutSession,
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function execute(Observer $observer): void
    {
        unset($observer);
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
        }

        $items = [];
        /** @var array<int, string> $categoryCache */
        $categoryCache = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $productId = (int) $item->getProductId();
            $parentItem = $item->getParentItem();
            $parentId = $parentItem ? (int) $parentItem->getProductId() : $productId;
            $variationId = $parentItem ? $productId : 0;

            $qty = (float) $item->getQty();
            $unitPrice = $qty > 0 ? (float) $item->getRowTotal() / $qty : (float) $item->getPrice();

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
                'product_id'   => $productId,
                'parent_id'    => $parentId,
                'variation_id' => $variationId,
                'title'        => $item->getName(),
                'sku'          => $item->getSku(),
                'category'     => $category,
                'quantity'     => $qty,
                'unit_price'   => $unitPrice,
            ];
        }

        $data = [
            'cart_id'    => (int) $quote->getId(),
            'cart_total' => (string) $quote->getGrandTotal(),
            'currency'   => (string) $quote->getQuoteCurrencyCode(),
            'item_count' => (int) $quote->getItemsCount(),
            'items'      => $items,
            'context'    => StorefrontContext::fromRequest($this->request, 'cart', 'Shopping Cart'),
        ];
        $this->dispatcher->dispatch('view_cart', $data);
    }
}
