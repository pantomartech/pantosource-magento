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

class CartAddObserver implements ObserverInterface
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
        $item = $observer->getEvent()->getQuoteItem();
        if (!$item || !$item->getProductId()) {
            return;
        }

        $qty = (float) $item->getQty();
        $rowTotal = (float) $item->getRowTotal();
        $unitPrice = $qty > 0 && $rowTotal > 0 ? $rowTotal / $qty : (float) $item->getPrice();
        $title = (string) $item->getName();
        $sku = (string) $item->getSku();
        $productId = (int) $item->getProductId();
        $category = $this->resolveCategory($item);

        try {
            $currency = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = '';
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
            'product_id' => $productId,
            'quantity' => (int) $qty,
            'sku' => $sku,
            'title' => $title,
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'quote_item_id' => (int) $item->getId(),
            'category' => $category,
            'items' => [$line],
            'context' => StorefrontContext::fromRequest($this->request, 'cart', ''),
        ];
        if ($cartId > 0) {
            $data['cart_id'] = $cartId;
        }
        $this->dispatcher->dispatch('magento_add_to_cart', $data);
    }

    private function resolveCategory(mixed $item): string
    {
        try {
            $product = $item->getProduct();
            if ($product === null) {
                return '';
            }
            $catIds = $product->getCategoryIds();
            if (empty($catIds)) {
                return '';
            }
            return (string) ($this->categoryRepository->get((int) $catIds[0])->getName() ?? '');
        } catch (\Throwable) {
            return '';
        }
    }
}
