<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\DebugTimeline;
use Panto\Source\Model\EventDispatcher;

class CartUpdateObserver implements ObserverInterface
{
    public const SKIP_INFO = 'skipped_info';
    public const SKIP_NO_CART = 'skipped_no_cart';
    public const SKIP_NO_ORIG_QTY = 'skipped_no_orig_qty';
    public const SKIP_NO_DELTA = 'skipped_no_delta';
    public const OBSERVED = 'observed';

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly DebugTimeline $debugTimeline
    ) {
    }

    public function execute(Observer $observer): void
    {
        $info = $this->unwrapInfo($observer->getEvent()->getInfo());
        if ($info === null) {
            $this->markSkip(self::SKIP_INFO, []);
            return;
        }
        $cart = $observer->getEvent()->getCart();
        $quote = $cart && is_callable([$cart, 'getQuote']) ? $cart->getQuote() : null;
        if (!$quote) {
            $this->markSkip(self::SKIP_NO_CART, []);
            return;
        }
        foreach ($info as $itemId => $itemInfo) {
            $this->emitLine($quote, (int) $itemId, $itemInfo);
        }
    }

    /**
     * Magento Cart::updateItems dispatches info as a DataObject, not an array.
     *
     * @return array<int|string, mixed>|null
     */
    private function unwrapInfo(mixed $info): ?array
    {
        if (is_object($info) && method_exists($info, 'getData')) {
            try {
                $info = $info->getData();
            } catch (\Throwable) {
                return null;
            }
        }
        return is_array($info) ? $info : null;
    }

    private function emitLine(mixed $quote, int $itemId, mixed $itemInfo): void
    {
        if (!is_array($itemInfo) || !isset($itemInfo['qty'])) {
            return;
        }
        $item = $quote->getItemById($itemId);
        if (!$item) {
            return;
        }
        $newQty = (int) $itemInfo['qty'];
        $orig = method_exists($item, 'getOrigData') ? $item->getOrigData('qty') : null;
        if ($orig === null || $orig === '') {
            $this->markSkip(self::SKIP_NO_ORIG_QTY, ['item_id' => $itemId]);
            return;
        }
        $oldQty = (int) (float) $orig;
        $delta = $newQty - $oldQty;
        if ($delta === 0) {
            $this->markSkip(self::SKIP_NO_DELTA, ['item_id' => $itemId]);
            return;
        }
        $this->dispatcher->dispatch('magento_cart_item_quantity_changed', [
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
        ]);
        $this->markSkip(self::OBSERVED, ['item_id' => $itemId, 'quantity_delta' => $delta]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function markSkip(string $reason, array $context): void
    {
        $this->debugTimeline->append([
            'source' => 'cart_update',
            'hook_or_action' => $reason,
            'event_name' => 'magento_cart_item_quantity_changed',
            'event_id' => '',
            'status' => $reason,
            'context' => $context,
            'payload' => [],
        ]);
    }
}
