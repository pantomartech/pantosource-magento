<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;
use Panto\Source\Model\PaymentStatusNormalizer;

final class PaymentStatusNormalizerTest extends TestCase
{
    public function testCanceledAndClosedStates(): void
    {
        $this->assertSame('cancelled', PaymentStatusNormalizer::fromValues('canceled', 0, 100, 0));
        $this->assertSame('refunded', PaymentStatusNormalizer::fromValues('closed', 100, 100, 100));
    }

    public function testUnpaidStatesArePending(): void
    {
        foreach (['new', 'pending_payment', 'payment_review', 'holded', 'processing'] as $state) {
            $this->assertSame('pending', PaymentStatusNormalizer::fromValues($state, 0, 100, 0), $state);
        }
    }

    public function testPaidAndRefundedTotals(): void
    {
        $this->assertSame('paid', PaymentStatusNormalizer::fromValues('processing', 100, 100, 0));
        $this->assertSame('pending', PaymentStatusNormalizer::fromValues('processing', 40, 100, 0));
        $this->assertSame('partially_refunded', PaymentStatusNormalizer::fromValues('complete', 100, 100, 25));
        $this->assertSame('refunded', PaymentStatusNormalizer::fromValues('complete', 100, 100, 100));
    }

    public function testFromOrderAndOrigData(): void
    {
        $order = new class ([
            'state' => 'processing',
            'total_paid' => 50,
            'grand_total' => 50,
            'total_refunded' => 0,
        ]) extends DataObject {
            public function getOrigData(string $key): mixed
            {
                return ['state' => 'new', 'total_paid' => 0, 'grand_total' => 50, 'total_refunded' => 0][$key] ?? null;
            }
        };
        $this->assertSame('paid', PaymentStatusNormalizer::fromOrder($order));
        $this->assertSame('pending', PaymentStatusNormalizer::fromOrigData($order));
    }

    public function testNewOrderHasNoPreviousStatus(): void
    {
        $order = new class (['state' => 'new']) extends DataObject {
            public function getOrigData(string $key): mixed
            {
                return null;
            }
        };
        $this->assertNull(PaymentStatusNormalizer::fromOrigData($order));
    }
}
