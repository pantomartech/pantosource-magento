<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\PageEngagementFields;

final class PageEngagementFieldsTest extends TestCase
{
    public function testKeepsValuesInRangeAsInts(): void
    {
        $out = PageEngagementFields::sanitize(['engaged_ms' => '4500', 'max_scroll_pct' => 62.4, 'source' => 'frontend_js']);
        $this->assertSame(4500, $out['engaged_ms']);
        $this->assertSame(62, $out['max_scroll_pct']);
        $this->assertSame('frontend_js', $out['source']);
    }

    public function testClampsOutOfRangeValues(): void
    {
        $out = PageEngagementFields::sanitize(['engaged_ms' => 1e30, 'max_scroll_pct' => 250]);
        $this->assertSame(PageEngagementFields::MAX_ENGAGED_MS, $out['engaged_ms']);
        $this->assertSame(100, $out['max_scroll_pct']);

        $out = PageEngagementFields::sanitize(['engaged_ms' => -5, 'max_scroll_pct' => -1]);
        $this->assertSame(0, $out['engaged_ms']);
        $this->assertSame(0, $out['max_scroll_pct']);
    }

    public function testDropsNonNumericValues(): void
    {
        $out = PageEngagementFields::sanitize(['engaged_ms' => 'lots', 'max_scroll_pct' => ['x']]);
        $this->assertArrayNotHasKey('engaged_ms', $out);
        $this->assertArrayNotHasKey('max_scroll_pct', $out);
    }

    public function testLeavesMissingKeysMissing(): void
    {
        $this->assertSame([], PageEngagementFields::sanitize([]));
    }
}
