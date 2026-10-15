<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\OrderLineSerializer;

final class OrderLineSerializerDiscountTest extends TestCase
{
    public function testNegativeMagentoDiscountIsSentAsMagnitude(): void
    {
        $this->assertSame('12.5', OrderLineSerializer::discountMagnitude(-12.5));
        $this->assertSame('12.5', OrderLineSerializer::discountMagnitude('-12.5000'));
    }

    public function testZeroAndMissingDiscountStayZero(): void
    {
        $this->assertSame('0', OrderLineSerializer::discountMagnitude(0));
        $this->assertSame('0', OrderLineSerializer::discountMagnitude(null));
    }
}
