<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use Panto\Source\Model\CartAddEmitter;
use Panto\Source\Observer\CartAddControllerObserver;

final class CartAddControllerObserverTest extends TestCase
{
    public function testEmitsMatchingQuoteItemFromProductParam(): void
    {
        $item = new class {
            public function getProductId(): int
            {
                return 1;
            }
        };
        $quote = new class ($item) {
            public function __construct(private mixed $item)
            {
            }

            public function getAllVisibleItems(): array
            {
                return [$this->item];
            }
        };
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturn(1);
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn('frontend');
        $emitter = $this->createMock(CartAddEmitter::class);
        $emitter->expects($this->once())->method('emitFromQuoteItem')->with($item);
        $observer = new CartAddControllerObserver($emitter, $request, $session, $state);
        $observer->execute($this->createMock(Observer::class));
    }

    public function testSkipsAdminhtml(): void
    {
        $session = $this->createMock(CheckoutSession::class);
        $request = $this->createMock(HttpRequest::class);
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn('adminhtml');
        $emitter = $this->createMock(CartAddEmitter::class);
        $emitter->expects($this->once())->method('markSkip')->with(CartAddEmitter::SKIP_ADMIN, []);
        $emitter->expects($this->never())->method('emitFromQuoteItem');
        $observer = new CartAddControllerObserver($emitter, $request, $session, $state);
        $observer->execute($this->createMock(Observer::class));
    }
}
