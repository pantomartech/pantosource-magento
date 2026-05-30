<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class ProductViewObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        unset($observer);
        $product = $this->registry->registry('current_product');
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
            'product_name' => (string) $product->getName(),
            'sku' => (string) $product->getSku(),
            'price' => (string) $product->getFinalPrice(),
            'currency' => $currency,
            'context' => StorefrontContext::fromRequest($this->request, 'product', (string) $product->getName()),
        ];
        $this->dispatcher->dispatch('product_viewed', $data);
    }
}
