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

    public function testPageEngagementBeaconContract(): void
    {
        $source = $this->trackerSource();
        $this->assertStringContainsString("var ENGAGEMENT_EVENT = 'magento_page_engagement';", $source);
        $this->assertStringContainsString('var ENGAGEMENT_IDLE_MS = 30000;', $source);
        $this->assertStringContainsString('var ENGAGEMENT_MIN_MS = 1000;', $source);
        $this->assertStringContainsString('if (ms < ENGAGEMENT_MIN_MS)', $source);
        $this->assertStringContainsString('engaged_ms: ms', $source);
        $this->assertStringContainsString('max_scroll_pct: Math.round(maxScrollPct)', $source);
        $this->assertStringContainsString("document.visibilityState === 'hidden'", $source);
        $this->assertStringContainsString("window.addEventListener('pagehide', flush);", $source);
        $this->assertStringContainsString('if (flushed) {', $source);
        $this->assertStringContainsString("new Blob([body], { type: 'application/json' })", $source);
        $this->assertStringContainsString('navigator.sendBeacon(request.url', $source);
        $this->assertStringContainsString('keepalive: true', $source);
        $this->assertStringContainsString('setupPageEngagement(cfg);', $source);
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
