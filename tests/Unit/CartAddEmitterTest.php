<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\GuestCartItemRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\CartAddEmissionGate;
use Panto\Source\Model\CartAddEmitter;
use Panto\Source\Model\DebugTimeline;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Plugin\CartItemRepositorySavePlugin;
use Panto\Source\Plugin\GuestCartItemRepositorySavePlugin;

final class CartAddEmitterTest extends TestCase
{
    public function testEmitsOnceThenSuppressesSameItem(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(
            'magento_add_to_cart',
            $this->callback(static function (array $data): bool {
                return ($data['product_id'] ?? null) === 1 && ($data['cart_id'] ?? null) === 7;
            })
        );
        $emitter = $this->emitter($dispatcher);
        $item = new FakeQuoteItem();
        $this->assertTrue($emitter->emitFromQuoteItem($item));
        $this->assertFalse($emitter->emitFromQuoteItem($item));
    }

    public function testSkipsWhenItemMissing(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $emitter = $this->emitter($dispatcher);
        $this->assertFalse($emitter->emitFromQuoteItem(null));
    }

    public function testRepositorySaveEmitsForNewItem(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch');
        $emitter = $this->emitter($dispatcher);
        $input = new FakeQuoteItem(id: 0);
        $result = new FakeQuoteItem(id: 12);
        $this->assertTrue($emitter->emitAfterRepositorySave($input, $result));
    }

    public function testRepositorySaveEmitsOnQtyIncrease(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch');
        $emitter = $this->emitter($dispatcher);
        $input = new FakeQuoteItem(id: 9, qty: 2.0, origQty: 1.0);
        $result = new FakeQuoteItem(id: 9, qty: 2.0);
        $this->assertTrue($emitter->emitAfterRepositorySave($input, $result));
    }

    public function testRepositorySaveSkipsQtyDecrease(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $emitter = $this->emitter($dispatcher);
        $input = new FakeQuoteItem(id: 9, qty: 1.0, origQty: 2.0);
        $result = new FakeQuoteItem(id: 9, qty: 1.0);
        $this->assertFalse($emitter->emitAfterRepositorySave($input, $result));
    }

    public function testPluginDelegatesToEmitter(): void
    {
        $emitter = $this->createMock(CartAddEmitter::class);
        $plugin = new CartItemRepositorySavePlugin($emitter);
        $repo = $this->createMock(CartItemRepositoryInterface::class);
        $input = new FakeQuoteItem(id: 0);
        $result = new FakeQuoteItem(id: 4);
        $emitter->expects($this->once())->method('emitAfterRepositorySave')->with($input, $result);
        $this->assertSame($result, $plugin->afterSave($repo, $result, $input));
    }

    public function testGuestPluginDelegatesToEmitter(): void
    {
        $emitter = $this->createMock(CartAddEmitter::class);
        $plugin = new GuestCartItemRepositorySavePlugin($emitter);
        $repo = $this->createMock(GuestCartItemRepositoryInterface::class);
        $input = new FakeQuoteItem(id: 0);
        $result = new FakeQuoteItem(id: 4);
        $emitter->expects($this->once())->method('emitAfterRepositorySave')->with($input, $result);
        $this->assertSame($result, $plugin->afterSave($repo, $result, $input));
    }

    public function testEmitsWhenProductIdIsMagicGetterOnly(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(
            'magento_add_to_cart',
            $this->callback(static function (array $data): bool {
                return ($data['product_id'] ?? null) === 1;
            })
        );
        $emitter = $this->emitter($dispatcher);
        $this->assertTrue($emitter->emitFromQuoteItem(new MagicQuoteItem([
            'product_id' => 1,
            'qty' => 1.0,
            'item_id' => 9,
            'quote_id' => 7,
            'name' => 'Test Product',
            'sku' => 'SKU',
            'price' => 50.0,
            'row_total' => 50.0,
        ])));
        $this->assertFalse(method_exists(new MagicQuoteItem(['product_id' => 1]), 'getProductId'));
    }

    public function testRepositorySaveQtyIncreaseViaGetData(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch');
        $emitter = $this->emitter($dispatcher);
        $input = new MagicQuoteItem([
            'product_id' => 1,
            'qty' => 2.0,
            'item_id' => 9,
            'quote_id' => 7,
        ], origQty: 1.0);
        $result = new MagicQuoteItem([
            'product_id' => 1,
            'qty' => 2.0,
            'item_id' => 9,
            'quote_id' => 7,
            'name' => 'Test Product',
            'sku' => 'SKU',
            'price' => 50.0,
        ]);
        $this->assertFalse(method_exists($result, 'getQty'));
        $this->assertTrue($emitter->emitAfterRepositorySave($input, $result));
    }

    private function emitter(EventDispatcher $dispatcher): CartAddEmitter
    {
        $store = new class {
            public function getCurrentCurrencyCode(): string
            {
                return 'USD';
            }
        };
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $timeline = $this->createMock(DebugTimeline::class);
        $timeline->method('append');
        $request = $this->requestBag();

        return new CartAddEmitter(
            $dispatcher,
            $request,
            $storeManager,
            $this->createMock(CategoryRepositoryInterface::class),
            new CartAddEmissionGate($request),
            $timeline
        );
    }

    private function requestBag(): HttpRequest
    {
        $store = [];
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(
            static function ($key, $default = null) use (&$store) {
                return $store[$key] ?? $default;
            }
        );
        $request->method('setParam')->willReturnCallback(
            static function ($key, $value) use (&$store, $request) {
                $store[$key] = $value;
                return $request;
            }
        );
        $request->method('getRequestUri')->willReturn('/checkout/cart/add');
        $request->method('getServer')->willReturn('');
        return $request;
    }
}

final class FakeQuoteItem
{
    public function __construct(
        public int $productId = 1,
        public float $qty = 1.0,
        public int $id = 9,
        public int $quoteId = 7,
        public float $rowTotal = 50.0,
        public float $price = 50.0,
        public string $name = 'Test Product',
        public string $sku = 'SKU',
        public ?float $origQty = null
    ) {
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getQty(): float
    {
        return $this->qty;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getItemId(): int
    {
        return $this->id;
    }

    public function getQuoteId(): int
    {
        return $this->quoteId;
    }

    public function getQuote(): mixed
    {
        return null;
    }

    public function getRowTotal(): float
    {
        return $this->rowTotal;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getProduct(): mixed
    {
        return null;
    }

    public function getOrigData(string $key): mixed
    {
        return $key === 'qty' ? $this->origQty : null;
    }
}

/**
 * Magento Quote\Item shaped: getData / __call only — no real getProductId() or getQty().
 */
final class MagicQuoteItem
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
        private ?float $origQty = null
    ) {
    }

    public function getData(?string $key = null): mixed
    {
        if ($key === null || $key === '') {
            return $this->data;
        }
        return $this->data[$key] ?? null;
    }

    public function getOrigData(string $key): mixed
    {
        return $key === 'qty' ? $this->origQty : null;
    }

    public function getQuote(): mixed
    {
        return null;
    }

    public function getProduct(): mixed
    {
        return null;
    }

    public function __call(string $name, array $arguments): mixed
    {
        unset($arguments);
        $map = [
            'getProductId' => 'product_id',
            'getQty' => 'qty',
            'getItemId' => 'item_id',
            'getId' => 'item_id',
            'getQuoteId' => 'quote_id',
            'getName' => 'name',
            'getSku' => 'sku',
            'getPrice' => 'price',
            'getRowTotal' => 'row_total',
        ];
        if (isset($map[$name])) {
            return $this->data[$map[$name]] ?? null;
        }
        return null;
    }
}
