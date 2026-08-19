<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\MatchingSignalFields;

final class MatchingSignalFieldsTest extends TestCase
{
    public function testUnionKeepsPreviousFbpWhenIncomingBlank(): void
    {
        $merged = MatchingSignalFields::union(
            ['utm_source' => 'facebook', 'fbp' => 'fb.1.1710000000.1234567890'],
            ['utm_source' => 'google']
        );
        $this->assertSame('fb.1.1710000000.1234567890', $merged['fbp']);
        $this->assertSame('facebook', $merged['utm_source']);
    }

    public function testUnionIncomingFbpWins(): void
    {
        $merged = MatchingSignalFields::union(
            ['fbp' => 'fb.1.old'],
            ['fbp' => 'fb.1.new']
        );
        $this->assertSame('fb.1.new', $merged['fbp']);
    }

    public function testCookiesPreferDataThenLastTouch(): void
    {
        $cookies = MatchingSignalFields::cookiesFromSources([
            ['fbp' => 'from-data'],
            ['fbp' => 'from-last', 'ttp' => 'ttp-1'],
        ]);
        $this->assertSame('from-data', $cookies['fbp']);
        $this->assertSame('ttp-1', $cookies['ttp']);
    }

    public function testFillFromAttributionCopiesMissingFbp(): void
    {
        $data = MatchingSignalFields::fillFromAttribution([
            'context' => [
                'attribution' => [
                    'last_touch' => ['fbp' => 'fb.1.1710000000.1234567890'],
                ],
            ],
        ]);
        $this->assertSame('fb.1.1710000000.1234567890', $data['fbp']);
    }

    public function testFirstValidIpParsesForwardedFor(): void
    {
        $this->assertSame('198.51.100.10', MatchingSignalFields::firstValidIp([
            '',
            '198.51.100.10, 10.0.0.1',
        ]));
    }
}
