<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\CheckoutInitSessionKey;

final class CheckoutInitSessionKeyTest extends TestCase
{
    public function testKeysAreQuoteScoped(): void
    {
        $this->assertSame('pantosource_checkout_init_sent_7', CheckoutInitSessionKey::forQuote(7));
        $this->assertNotSame(
            CheckoutInitSessionKey::forQuote(7),
            CheckoutInitSessionKey::forQuote(8)
        );
    }
}
