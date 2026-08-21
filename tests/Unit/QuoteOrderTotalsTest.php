<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\QuoteOrderTotals;

final class QuoteOrderTotalsTest extends TestCase
{
    public function testEmptyWhenQuoteMissing(): void
    {
        $this->assertSame(
            ['tax_total' => 0.0, 'shipping_total' => 0.0, 'discount_total' => 0.0],
            QuoteOrderTotals::fromQuote(null)
        );
    }

    public function testReadsShippingAddressTotals(): void
    {
        $quote = new class {
            public function getShippingAddress(): object
            {
                return new class {
                    public function getTaxAmount(): float
                    {
                        return 3.25;
                    }
                    public function getShippingAmount(): float
                    {
                        return 8.0;
                    }
                    public function getDiscountAmount(): float
                    {
                        return -1.5;
                    }
                };
            }
        };

        $this->assertSame(
            ['tax_total' => 3.25, 'shipping_total' => 8.0, 'discount_total' => 1.5],
            QuoteOrderTotals::fromQuote($quote)
        );
    }
}
