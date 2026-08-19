<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\QuoteLineIds;

final class QuoteLineIdsTest extends TestCase
{
    public function testSimpleItemHasNoVariant(): void
    {
        $item = new class {
            public function getProductId(): int
            {
                return 11;
            }
        };
        $this->assertSame(0, QuoteLineIds::variantId($item, 11));
    }

    public function testConfigurableChildOptionValue(): void
    {
        $item = new class {
            public function getOptionByCode(string $code): object
            {
                unset($code);
                return new class {
                    public function getValue(): int
                    {
                        return 22;
                    }
                };
            }
        };
        $this->assertSame(22, QuoteLineIds::variantId($item, 11));
    }

    public function testConfigurableChildFromGetChildren(): void
    {
        $item = new class {
            public function getChildren(): array
            {
                return [
                    new class {
                        public function getProductId(): int
                        {
                            return 22;
                        }
                    },
                ];
            }
        };
        $this->assertSame(22, QuoteLineIds::variantId($item, 11));
    }
}
