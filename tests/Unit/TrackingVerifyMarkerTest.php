<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\TrackingVerifyMarker;

final class TrackingVerifyMarkerTest extends TestCase
{
    private const PLAIN_API_KEY = 'dummy0dummy0dummy0dummy0dummy0dummy0dummy0dummy0dummy0dummy0du';

    public function testBuildReturnsEmptyForMissingOrInvalidKey(): void
    {
        $marker = new TrackingVerifyMarker();
        $this->assertSame('', $marker->build(''));
        $this->assertSame('', $marker->build('short'));
        $this->assertSame('', $marker->build(str_repeat('x', 61)));
    }

    public function testBuildReturnsSha256HtmlComment(): void
    {
        $marker = new TrackingVerifyMarker();
        $expected = '<!-- code: ' . hash('sha256', self::PLAIN_API_KEY) . ' -->';
        $this->assertSame($expected, $marker->build(self::PLAIN_API_KEY));
        $this->assertSame(62, strlen(self::PLAIN_API_KEY));
    }
}
