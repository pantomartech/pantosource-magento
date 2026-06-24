<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\AttributionMetaBuilder;

final class AttributionMetaBuilderTest extends TestCase
{
    public function testLookbackDaysConstant(): void
    {
        $this->assertSame(30, AttributionMetaBuilder::ATTRIBUTION_LOOKBACK_DAYS);
    }

    public function testInfersClickIdSource(): void
    {
        $source = AttributionMetaBuilder::inferAttributionSource(['gclid' => 'abc']);
        $this->assertSame('click_id', $source);
    }

    public function testInfersUtmSource(): void
    {
        $source = AttributionMetaBuilder::inferAttributionSource(['utm_source' => 'google']);
        $this->assertSame('utm', $source);
    }

    public function testBuildMetaOmittedWhenTouchesEmpty(): void
    {
        $meta = AttributionMetaBuilder::buildAttributionMeta([], []);
        $this->assertNull($meta);
    }

    public function testBuildMetaPresentWhenLastTouchHasUtm(): void
    {
        $meta = AttributionMetaBuilder::buildAttributionMeta(
            [],
            ['utm_source' => 'newsletter', 'captured_at' => '2026-01-01T00:00:00.000Z']
        );
        $this->assertIsArray($meta);
        $this->assertSame('utm', $meta['attribution_source']);
        $this->assertSame(30, $meta['lookback_days']);
    }
}
