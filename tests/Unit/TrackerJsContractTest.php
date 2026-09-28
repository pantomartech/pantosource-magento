<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TrackerJsContractTest extends TestCase
{
    public function testTrackerReadsVendorAdCookies(): void
    {
        $path = dirname(__DIR__, 2) . '/view/frontend/web/js/tracker.js';
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertNotFalse($source);
        foreach (['_ttp', '_fbp', '_fbc', '_epik', '_scid', '_twclid', '__oppref', '_uetmsclkid'] as $cookieName) {
            $this->assertStringContainsString("cookie: '{$cookieName}'", $source);
        }
    }
}
