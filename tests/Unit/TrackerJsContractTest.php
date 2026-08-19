<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TrackerJsContractTest extends TestCase
{
    public function testTrackerReadsTtpCookie(): void
    {
        $path = dirname(__DIR__, 2) . '/view/frontend/web/js/tracker.js';
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertNotFalse($source);
        $this->assertStringContainsString("readCookie('_ttp')", $source);
        $this->assertStringContainsString("readCookie('_fbp')", $source);
        $this->assertStringContainsString("readCookie('_fbc')", $source);
    }
}
