<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CartRemoveObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request
    ) {
    }

    public function execute(Observer $observer): void
    {
        $item = $observer->getEvent()->getQuoteItem();
        if (!$item) {
            return;
        }
        $data = [
            'quote_item_id' => (int) $item->getId(),
            'product_id' => (int) $item->getProductId(),
            'quantity' => (int) $item->getQty(),
            'sku' => (string) $item->getSku(),
            'context' => StorefrontContext::fromRequest($this->request, 'cart', ''),
        ];
        $this->dispatcher->dispatch('magento_remove_from_cart', $data);
    }
}
