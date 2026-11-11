<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Wishlist\Model\Item;
use Magento\Wishlist\Model\ItemFactory;
use PHPUnit\Framework\TestCase;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Observer\WishlistRemoveObserver;

final class WishlistRemoveObserverTest extends TestCase
{
    private const ITEM_ID = 15;

    public function testEmitsAfterItemIsDeleted(): void
    {
        $factory = $this->factoryWithItem();
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(
            'magento_remove_from_wishlist',
            $this->callback(static function (array $data): bool {
                return $data['product_id'] === 42
                    && $data['sku'] === 'WSH-1'
                    && $data['wishlist_id'] === 7
                    && ($data['context']['page_type'] ?? null) === 'wishlist';
            })
        );
        $observer = $this->observer($dispatcher, $factory, WishlistRemoveObserver::FULL_ACTION_NAME, self::ITEM_ID);

        $observer->execute($this->event(WishlistRemoveObserver::PREDISPATCH_EVENT));
        $factory->items = [];
        $observer->execute($this->event(WishlistRemoveObserver::POSTDISPATCH_EVENT));
    }

    public function testSkipsWhenItemStillExists(): void
    {
        $factory = $this->factoryWithItem();
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $observer = $this->observer($dispatcher, $factory, WishlistRemoveObserver::FULL_ACTION_NAME, self::ITEM_ID);

        $observer->execute($this->event(WishlistRemoveObserver::PREDISPATCH_EVENT));
        $observer->execute($this->event(WishlistRemoveObserver::POSTDISPATCH_EVENT));
    }

    public function testSkipsUnknownItem(): void
    {
        $factory = new FakeWishlistItemFactory();
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $observer = $this->observer($dispatcher, $factory, WishlistRemoveObserver::FULL_ACTION_NAME, 999);

        $observer->execute($this->event(WishlistRemoveObserver::PREDISPATCH_EVENT));
        $observer->execute($this->event(WishlistRemoveObserver::POSTDISPATCH_EVENT));
    }

    public function testSkipsOtherActions(): void
    {
        $factory = $this->factoryWithItem();
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $observer = $this->observer($dispatcher, $factory, 'wishlist_index_index', self::ITEM_ID);

        $observer->execute($this->event(WishlistRemoveObserver::PREDISPATCH_EVENT));
        $factory->items = [];
        $observer->execute($this->event(WishlistRemoveObserver::POSTDISPATCH_EVENT));
    }

    public function testPostdispatchWithoutCaptureSendsNothing(): void
    {
        $factory = new FakeWishlistItemFactory();
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $observer = $this->observer($dispatcher, $factory, WishlistRemoveObserver::FULL_ACTION_NAME, self::ITEM_ID);

        $observer->execute($this->event(WishlistRemoveObserver::POSTDISPATCH_EVENT));
    }

    private function factoryWithItem(): FakeWishlistItemFactory
    {
        $factory = new FakeWishlistItemFactory();
        $factory->items[self::ITEM_ID] = ['product_id' => 42, 'wishlist_id' => 7, 'sku' => 'WSH-1'];
        return $factory;
    }

    private function observer(
        EventDispatcher $dispatcher,
        ItemFactory $factory,
        string $fullActionName,
        int $itemParam
    ): WishlistRemoveObserver {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getFullActionName')->willReturn($fullActionName);
        $request->method('getParam')->willReturnCallback(
            static fn ($key, $default = null) => $key === 'item' ? (string) $itemParam : $default
        );
        $request->method('getRequestUri')->willReturn('/wishlist/index/remove/');
        $request->method('getServer')->willReturn('');
        return new WishlistRemoveObserver($dispatcher, $request, $factory);
    }

    private function event(string $name): Observer
    {
        $event = new class ($name) {
            public function __construct(private string $name)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }
        };
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);
        return $observer;
    }
}

final class FakeWishlistItemFactory extends ItemFactory
{
    /** @var array<int, array{product_id:int, wishlist_id:int, sku:string}> */
    public array $items = [];

    public function create(): Item
    {
        return new FakeWishlistItem($this);
    }
}

final class FakeWishlistItem extends Item
{
    /** @var array{product_id:int, wishlist_id:int, sku:string}|null */
    private ?array $row = null;

    private int $id = 0;

    public function __construct(private readonly FakeWishlistItemFactory $factory)
    {
    }

    public function load($modelId)
    {
        $this->row = $this->factory->items[(int) $modelId] ?? null;
        $this->id = $this->row === null ? 0 : (int) $modelId;
        return $this;
    }

    public function getId(): mixed
    {
        return $this->id ?: null;
    }

    public function getProductId(): mixed
    {
        return $this->row['product_id'] ?? null;
    }

    public function getWishlistId(): mixed
    {
        return $this->row['wishlist_id'] ?? null;
    }

    public function getProduct(): mixed
    {
        $sku = $this->row['sku'] ?? '';
        return new class ($sku) {
            public function __construct(private string $sku)
            {
            }

            public function getSku(): string
            {
                return $this->sku;
            }
        };
    }
}
