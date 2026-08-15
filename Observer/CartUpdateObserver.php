<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CartUpdateObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request
    ) {
    }

    public function execute(Observer $observer): void
    {
        $info = $observer->getEvent()->getInfo();
        if (!is_array($info)) {
            return;
        }
        $cart = $observer->getEvent()->getCart();
        if (!$cart) {
            return;
        }
        $quote = $cart->getQuote();
        if (!$quote) {
            return;
        }
        foreach ($info as $itemId => $itemInfo) {
            if (!is_array($itemInfo) || !isset($itemInfo['qty'])) {
                continue;
            }
            $itemId = (int) $itemId;
            $item = $quote->getItemById($itemId);
            if (!$item) {
                continue;
            }
            $newQty = (int) $itemInfo['qty'];
            $orig = $item->getOrigData('qty');
            if ($orig === null) {
                continue;
            }
            $oldQty = (int) (float) $orig;
            $delta = $newQty - $oldQty;
            if ($delta === 0) {
                continue;
            }
            $data = [
                'quote_item_id' => $itemId,
                'new_quantity' => $newQty,
                'old_quantity' => $oldQty,
                'quantity_delta' => $delta,
                'product_id' => (int) $item->getProductId(),
                'sku' => (string) $item->getSku(),
                'title' => (string) $item->getName(),
                'unit_price' => (float) $item->getPrice(),
                'currency' => (string) $quote->getQuoteCurrencyCode(),
                'context' => StorefrontContext::fromRequest($this->request, 'cart', ''),
            ];
            $this->dispatcher->dispatch('magento_cart_item_quantity_changed', $data);
        }
    }
}
