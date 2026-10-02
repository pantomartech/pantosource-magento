<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use Panto\Source\Model\ConversionDedupeRepository;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Observer\OrderPaymentStatusObserver;

final class OrderPaymentStatusObserverTest extends TestCase
{
    public function testEmitsWhenPendingOrderBecomesPaid(): void
    {
        $repo = $this->repo(purchaseEmitted: true, alreadyEmitted: false);
        $repo->expects($this->once())->method('markEmitted')->with(7, 'paid');
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(
            'magento_order_payment_status_changed',
            $this->callback(static function (array $data): bool {
                return $data['payment_status'] === 'paid'
                    && $data['previous_payment_status'] === 'pending'
                    && $data['occurrence'] === 'paid'
                    && $data['status'] === 'processing';
            }),
            'track'
        )->willReturn(true);

        $this->observer($dispatcher, $repo)->execute($this->eventFor($this->paidOrder()));
    }

    public function testSkipsBeforePurchaseWasSent(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $this->observer($dispatcher, $this->repo(purchaseEmitted: false, alreadyEmitted: false))
            ->execute($this->eventFor($this->paidOrder()));
    }

    public function testSkipsStatusAlreadySent(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $this->observer($dispatcher, $this->repo(purchaseEmitted: true, alreadyEmitted: true))
            ->execute($this->eventFor($this->paidOrder()));
    }

    public function testSkipsUnchangedStatus(): void
    {
        $order = new class (['entity_id' => 7, 'state' => 'processing', 'total_paid' => 0, 'grand_total' => 50]) extends DataObject {
            public function getId(): int
            {
                return 7;
            }

            public function getOrigData(string $key): mixed
            {
                return ['state' => 'new', 'total_paid' => 0, 'grand_total' => 50][$key] ?? null;
            }
        };
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $this->observer($dispatcher, $this->repo(purchaseEmitted: true, alreadyEmitted: false))
            ->execute($this->eventFor($order));
    }

    private function paidOrder(): object
    {
        return new class (['state' => 'processing', 'total_paid' => 50, 'grand_total' => 50, 'total_refunded' => 0]) extends DataObject {
            public function getId(): int
            {
                return 7;
            }

            public function getGrandTotal(): float
            {
                return 50.0;
            }

            public function getOrderCurrencyCode(): string
            {
                return 'EUR';
            }

            public function getOrigData(string $key): mixed
            {
                return ['state' => 'new', 'total_paid' => 0, 'grand_total' => 50, 'total_refunded' => 0][$key] ?? null;
            }
        };
    }

    private function repo(bool $purchaseEmitted, bool $alreadyEmitted): ConversionDedupeRepository
    {
        $repo = $this->createMock(ConversionDedupeRepository::class);
        $repo->method('isPurchaseEmitted')->willReturn($purchaseEmitted);
        $repo->method('hasEmitted')->willReturn($alreadyEmitted);
        return $repo;
    }

    private function observer(EventDispatcher $dispatcher, ConversionDedupeRepository $repo): OrderPaymentStatusObserver
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestUri')->willReturn('/admin/sales/order/');
        $request->method('getServer')->willReturn('');
        return new OrderPaymentStatusObserver($dispatcher, $request, $repo);
    }

    private function eventFor(object $order): Observer
    {
        $event = new class ($order) {
            public function __construct(private object $order)
            {
            }

            public function getOrder(): object
            {
                return $this->order;
            }
        };
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);
        return $observer;
    }
}
