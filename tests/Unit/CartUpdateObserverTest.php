<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use Panto\Source\Model\DebugTimeline;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Observer\CartUpdateObserver;

final class CartUpdateObserverTest extends TestCase
{
    public function testDispatchesWhenInfoIsDataObject(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(
            'magento_cart_item_quantity_changed',
            $this->callback(static function (array $data): bool {
                return ($data['quantity_delta'] ?? null) === 2
                    && ($data['old_quantity'] ?? null) === 1
                    && ($data['new_quantity'] ?? null) === 3;
            })
        );
        $observer = $this->cartObserver(
            new DataObject([9 => ['qty' => 3]]),
            $this->quoteWithItem(9, origQty: 1.0)
        );
        $this->updateObserver($dispatcher)->execute($observer);
    }

    public function testDispatchesWhenInfoIsArray(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(
            'magento_cart_item_quantity_changed',
            $this->callback(static function (array $data): bool {
                return ($data['quantity_delta'] ?? null) === 1;
            })
        );
        $observer = $this->cartObserver(
            [9 => ['qty' => 2]],
            $this->quoteWithItem(9, origQty: 1.0)
        );
        $this->updateObserver($dispatcher)->execute($observer);
    }

    public function testDoesNotDispatchWhenQtyUnchanged(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $observer = $this->cartObserver(
            new DataObject([9 => ['qty' => 1]]),
            $this->quoteWithItem(9, origQty: 1.0)
        );
        $this->updateObserver($dispatcher)->execute($observer);
    }

    public function testDoesNotDispatchWhenOrigQtyMissing(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $observer = $this->cartObserver(
            new DataObject([9 => ['qty' => 3]]),
            $this->quoteWithItem(9, origQty: null)
        );
        $this->updateObserver($dispatcher)->execute($observer);
    }

    private function updateObserver(EventDispatcher $dispatcher): CartUpdateObserver
    {
        $timeline = $this->createMock(DebugTimeline::class);
        $timeline->method('append');
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestUri')->willReturn('/checkout/cart/');
        $request->method('getServer')->willReturn('');
        return new CartUpdateObserver($dispatcher, $request, $timeline);
    }

    private function cartObserver(mixed $info, mixed $quote): Observer
    {
        $cart = new class ($quote) {
            public function __construct(private mixed $quote)
            {
            }

            public function getQuote(): mixed
            {
                return $this->quote;
            }
        };
        $event = new class ($info, $cart) {
            public function __construct(private mixed $info, private mixed $cart)
            {
            }

            public function getInfo(): mixed
            {
                return $this->info;
            }

            public function getCart(): mixed
            {
                return $this->cart;
            }
        };
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);
        return $observer;
    }

    private function quoteWithItem(int $itemId, ?float $origQty): object
    {
        $item = new class ($itemId, $origQty) {
            public function __construct(
                private int $id,
                private ?float $origQty
            ) {
            }

            public function getProductId(): int
            {
                return 1;
            }

            public function getSku(): string
            {
                return 'SKU';
            }

            public function getName(): string
            {
                return 'Test Product';
            }

            public function getPrice(): float
            {
                return 50.0;
            }

            public function getOrigData(string $key): mixed
            {
                return $key === 'qty' ? $this->origQty : null;
            }
        };
        return new class ($itemId, $item) {
            public function __construct(private int $itemId, private mixed $item)
            {
            }

            public function getItemById(int $id): mixed
            {
                return $id === $this->itemId ? $this->item : null;
            }

            public function getQuoteCurrencyCode(): string
            {
                return 'USD';
            }
        };
    }
}
