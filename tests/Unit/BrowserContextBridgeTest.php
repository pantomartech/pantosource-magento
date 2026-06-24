<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\BrowserContextBridge;

final class BrowserContextBridgeTest extends TestCase
{
    public function testCookieNameConstant(): void
    {
        $this->assertSame('pantosource_browser_ctx', BrowserContextBridge::COOKIE_NAME);
    }
}
