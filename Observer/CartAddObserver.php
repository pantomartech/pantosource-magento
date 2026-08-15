<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Helper\CartAddEmissionGate;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CartAddObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly State $appState,
        private readonly CartAddEmissionGate $emissionGate
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            if ($this->appState->getAreaCode() === 'adminhtml') {
                return;
            }
        } catch (\Throwable) {
        }

        $item = $this->resolveQuoteItem($observer);
        if ($item === null || !method_exists($item, 'getProductId') || !$item->getProductId()) {
            return;
        }

        $qty = (float) $item->getQty();
        $rowTotal = (float) $item->getRowTotal();
        $unitPrice = $qty > 0 && $rowTotal > 0 ? $rowTotal / $qty : (float) $item->getPrice();
        $title = (string) $item->getName();
        $sku = (string) $item->getSku();
        $productId = (int) $item->getProductId();
        $category = $this->resolveCategory($item);

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

        $itemId = method_exists($item, 'getId') ? (int) $item->getId() : 0;
        if (!$this->emissionGate->shouldEmit(CartAddEmissionGate::dedupeKey($itemId, $cartId, $productId, $qty))) {
            return;
        }

        try {
            $currency = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = '';
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
            'quote_item_id' => $itemId,
            'category' => $category,
            'items' => [$line],
            'context' => StorefrontContext::fromRequest($this->request, 'cart', ''),
        ];
        if ($cartId > 0) {
            $data['cart_id'] = $cartId;
        }
        $this->dispatcher->dispatch('magento_add_to_cart', $data);
    }

    private function resolveQuoteItem(Observer $observer): mixed
    {
        $event = $observer->getEvent();
        $item = $event->getQuoteItem();
        if (is_object($item)) {
            return $item;
        }
        $items = $event->getItems();
        if (!is_array($items) || $items === []) {
            return null;
        }
        $last = $items[array_key_last($items)];
        return is_object($last) ? $last : null;
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
