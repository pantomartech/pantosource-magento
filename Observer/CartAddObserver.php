<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CartAddObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        if ($this->config->isFrontendTrackerEnabled($storeId)) {
            return;
        }

        $item = $observer->getEvent()->getQuoteItem();
        if (!$item || !$item->getProductId()) {
            return;
        }
        $data = [
            'product_id' => (int) $item->getProductId(),
            'quantity' => (int) $item->getQty(),
            'sku' => (string) $item->getSku(),
            'unit_price' => (float) $item->getPrice(),
            'currency' => (string) $this->storeManager->getStore()->getCurrentCurrencyCode(),
            'quote_item_id' => (int) $item->getId(),
            'context' => StorefrontContext::fromRequest($this->request, 'cart', ''),
        ];
        $this->dispatcher->dispatch('magento_add_to_cart', $data);
    }
}
