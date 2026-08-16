<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\PlatformString;

final class PlatformStringTest extends TestCase
{
    public function testStripsWrappingQuotes(): void
    {
        $this->assertSame('Windows', PlatformString::normalize('"Windows"'));
    }

    public function testLeavesUnquotedPlatform(): void
    {
        $this->assertSame('macOS', PlatformString::normalize('macOS'));
    }

    public function testEmptyString(): void
    {
        $this->assertSame('', PlatformString::normalize(''));
        $this->assertSame('', PlatformString::normalize('   '));
    }
}
