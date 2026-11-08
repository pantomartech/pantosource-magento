<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use Panto\Source\Model\CartAddEmitter;
use Panto\Source\Observer\CartAddSaveCommitObserver;

final class CartAddSaveCommitObserverTest extends TestCase
{
    public function testPassesSavedItemToEmitter(): void
    {
        $item = new \stdClass();
        $emitter = $this->createMock(CartAddEmitter::class);
        $emitter->expects($this->once())->method('emitPendingAfterSave')->with($item);
        $event = new class ($item) {
            public function __construct(private object $item)
            {
            }

            public function getItem(): object
            {
                return $this->item;
            }
        };
        $magentoObserver = $this->createMock(Observer::class);
        $magentoObserver->method('getEvent')->willReturn($event);
        (new CartAddSaveCommitObserver($emitter))->execute($magentoObserver);
    }
}
