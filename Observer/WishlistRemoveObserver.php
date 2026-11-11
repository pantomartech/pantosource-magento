<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Wishlist\Model\ItemFactory;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

/**
 * Emits magento_remove_from_wishlist for the storefront wishlist remove action
 * (wishlist/index/remove?item=<wishlist item id>).
 *
 * Magento dispatches no wishlist-specific remove event, and by postdispatch the item row is
 * already deleted. So this observer runs twice per request:
 *   - controller_action_predispatch_wishlist_index_remove: load the item and remember
 *     product id, sku and wishlist id;
 *   - controller_action_postdispatch_wishlist_index_remove: emit only when the item row is gone,
 *     so a rejected remove (unknown item, another customer's wishlist, failed delete) sends nothing.
 *
 * The captured item lives on this instance between the two events; that relies on the observer
 * staying shared (the events.xml default; do not set shared="false").
 */
class WishlistRemoveObserver implements ObserverInterface
{
    public const FULL_ACTION_NAME = 'wishlist_index_remove';
    public const PREDISPATCH_EVENT = 'controller_action_predispatch_wishlist_index_remove';
    public const POSTDISPATCH_EVENT = 'controller_action_postdispatch_wishlist_index_remove';
    private const ITEM_PARAM = 'item';

    /** @var array{item_id:int, product_id:int, sku:string, wishlist_id:int}|null */
    private ?array $captured = null;

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly ItemFactory $itemFactory
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->request->getFullActionName() !== self::FULL_ACTION_NAME) {
            return;
        }
        $eventName = (string) $observer->getEvent()->getName();
        if ($eventName === self::PREDISPATCH_EVENT) {
            $this->capture();
            return;
        }
        if ($eventName === self::POSTDISPATCH_EVENT) {
            $this->emitIfRemoved();
        }
    }

    private function capture(): void
    {
        $this->captured = null;
        $itemId = (int) $this->request->getParam(self::ITEM_PARAM, 0);
        if ($itemId <= 0) {
            return;
        }
        try {
            $item = $this->itemFactory->create()->load($itemId);
            if (!$item->getId() || (int) $item->getProductId() <= 0) {
                return;
            }
            $this->captured = [
                'item_id' => $itemId,
                'product_id' => (int) $item->getProductId(),
                'sku' => $this->itemSku($item),
                'wishlist_id' => (int) $item->getWishlistId(),
            ];
        } catch (\Throwable) {
            $this->captured = null;
        }
    }

    private function emitIfRemoved(): void
    {
        $captured = $this->captured;
        $this->captured = null;
        if ($captured === null || !$this->isDeleted($captured['item_id'])) {
            return;
        }
        $data = [
            'product_id' => $captured['product_id'],
            'sku' => $captured['sku'],
            'context' => StorefrontContext::fromRequest($this->request, 'wishlist', ''),
        ];
        if ($captured['wishlist_id'] > 0) {
            $data['wishlist_id'] = $captured['wishlist_id'];
        }
        $this->dispatcher->dispatch('magento_remove_from_wishlist', $data);
    }

    private function isDeleted(int $itemId): bool
    {
        try {
            return !$this->itemFactory->create()->load($itemId)->getId();
        } catch (\Throwable) {
            return false;
        }
    }

    private function itemSku(mixed $item): string
    {
        try {
            $product = $item->getProduct();
            return $product ? (string) $product->getSku() : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
