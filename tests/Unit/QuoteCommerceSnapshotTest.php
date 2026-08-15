<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\QuoteCommerceSnapshot;

final class QuoteCommerceSnapshotTest extends TestCase
{
    public function testEmptyWhenQuoteMissing(): void
    {
        $this->assertSame(
            ['cart_total' => '', 'currency' => '', 'items' => []],
            QuoteCommerceSnapshot::fromQuote(null)
        );
    }

    public function testMapsVisibleItemsAndTotals(): void
    {
        $item = new class {
            public function getProductId(): int
            {
                return 1;
            }
            public function getName(): string
            {
                return 'Test Product 1';
            }
            public function getSku(): string
            {
                return 'Test Product';
            }
            public function getQty(): float
            {
                return 1.0;
            }
            public function getRowTotal(): float
            {
                return 50.0;
            }
            public function getPrice(): float
            {
                return 50.0;
            }
        };
        $quote = new class ($item) {
            public function __construct(private readonly object $item)
            {
            }
            public function getId(): int
            {
                return 7;
            }
            public function getGrandTotal(): string
            {
                return '55.0000';
            }
            public function getQuoteCurrencyCode(): string
            {
                return 'USD';
            }
            public function getAllVisibleItems(): array
            {
                return [$this->item];
            }
        };

        $snapshot = QuoteCommerceSnapshot::fromQuote($quote);
        $this->assertSame('55.0000', $snapshot['cart_total']);
        $this->assertSame('USD', $snapshot['currency']);
        $this->assertCount(1, $snapshot['items']);
        $this->assertSame(1, $snapshot['items'][0]['product_id']);
        $this->assertSame(50.0, $snapshot['items'][0]['unit_price']);
    }
}
