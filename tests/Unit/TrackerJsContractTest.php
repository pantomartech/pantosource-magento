<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TrackerJsContractTest extends TestCase
{
    public function testTrackerReadsVendorAdCookies(): void
    {
        $source = $this->trackerSource();
        foreach (['_ttp', '_fbp', '_fbc', '_epik', '_scid', '_twclid', '__oppref', '_uetmsclkid'] as $cookieName) {
            $this->assertStringContainsString("cookie: '{$cookieName}'", $source);
        }
    }

    public function testSpaProductViewedContract(): void
    {
        $source = $this->trackerSource();
        $this->assertStringContainsString("panto_pv_occurred_at:", $source);
        $this->assertStringContainsString('rememberProductViewedOccurredAt', $source);
        $this->assertStringContainsString('maybeEmitSpaProductViewed', $source);
        $this->assertStringContainsString("postEvent(cfg, 'product_viewed'", $source);
        $this->assertStringContainsString('serverProductViewed', $source);
        $this->assertStringContainsString('fetchTrackerConfig', $source);
        $this->assertStringContainsString('page_path=', $source);
        $this->assertStringContainsString('occurred_at', $source);
    }

    public function testCampaignLandingReplacesSessionLanding(): void
    {
        $source = $this->trackerSource();
        $this->assertStringContainsString('function resolveSessionLanding()', $source);
        $this->assertStringContainsString('Object.keys(getAttributionFromUrl()).length > 0', $source);
        $this->assertStringContainsString('NON_LANDING_PATH', $source);
        $this->assertStringContainsString('var landing = resolveSessionLanding();', $source);
    }

    private function trackerSource(): string
    {
        $path = dirname(__DIR__, 2) . '/view/frontend/web/js/tracker.js';
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertNotFalse($source);
        return $source;
    }
}
