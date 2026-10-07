<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Helper\ProductViewedClaim;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\CurrentCatalogContext;
use Panto\Source\Model\EventDispatcher;

class ProductViewObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly CurrentCatalogContext $catalogContext,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductViewedClaim $productViewedClaim
    ) {
    }

    public function execute(Observer $observer): void
    {
        unset($observer);
        $product = $this->catalogContext->getCurrentProduct();
        if ($product === null) {
            return;
        }
        try {
            $currency = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = '';
        }
        $productId = (int) $product->getId();
        $data = [
            'product_id' => $productId,
            'product_name' => (string) $product->getName(),
            'sku' => (string) $product->getSku(),
            'price' => (string) $product->getFinalPrice(),
            'currency' => $currency,
            'context' => StorefrontContext::fromRequest($this->request, 'product', (string) $product->getName()),
        ];
        if ($this->dispatcher->dispatch('product_viewed', $data)) {
            $this->productViewedClaim->claim($productId);
        }
    }
}
