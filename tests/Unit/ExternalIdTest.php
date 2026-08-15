<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\ExternalId;

final class ExternalIdTest extends TestCase
{
    public function testGuestZeroIsEmpty(): void
    {
        $this->assertSame('', ExternalId::fromCustomerId(0));
        $this->assertSame('', ExternalId::fromCustomerId('0'));
        $this->assertSame('', ExternalId::fromCustomerId(null));
        $this->assertSame('', ExternalId::fromCustomerId(''));
    }

    public function testRegisteredIdIsPreserved(): void
    {
        $this->assertSame('42', ExternalId::fromCustomerId(42));
        $this->assertSame('42', ExternalId::fromCustomerId('42'));
    }
}
