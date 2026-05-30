<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

/**
 * Observer for wishlist_add_product — fires when a product is added to a wishlist.
 * Emits magento_add_to_wishlist.
 */
class WishlistAddObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        try {
            $currency = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = '';
        }

        $data = [
            'product_id' => (int) $product->getId(),
            'sku'        => (string) $product->getSku(),
            'unit_price' => (float) $product->getFinalPrice(),
            'currency'   => $currency,
            'context'    => StorefrontContext::fromRequest($this->request, 'product', (string) $product->getName()),
        ];
        $this->dispatcher->dispatch('magento_add_to_wishlist', $data);
    }
}
