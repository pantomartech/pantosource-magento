<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\CheckoutIdentitySnapshot;

final class CheckoutIdentitySnapshotTest extends TestCase
{
    public function testFromEventDataKeepsCookiesAndIp(): void
    {
        $snap = CheckoutIdentitySnapshot::fromEventData(
            [
                'fbp' => 'fb.1.1710000000.1234567890',
                'context' => [
                    'attribution' => [
                        'last_touch' => ['utm_source' => 'google', 'fbp' => 'fb.1.1710000000.1234567890'],
                    ],
                ],
            ],
            '198.51.100.10',
            'Mozilla/5.0 Magento'
        );
        $this->assertSame('google', $snap['last_touch']['utm_source']);
        $this->assertSame('fb.1.1710000000.1234567890', $snap['last_cookies']['fbp']);
        $this->assertSame('198.51.100.10', $snap['last_client_ip']);
        $this->assertSame('Mozilla/5.0 Magento', $snap['last_user_agent']);
    }

    public function testApplyFillsMissingFbpAndStoredIp(): void
    {
        $merged = CheckoutIdentitySnapshot::applyToEventData(
            ['order_id' => 1],
            [
                'last_touch' => ['utm_source' => 'facebook'],
                'last_cookies' => ['fbp' => 'fb.1.1710000000.1234567890'],
                'last_client_ip' => '198.51.100.10',
                'last_user_agent' => 'Mozilla/5.0 Storefront',
            ]
        );
        $this->assertSame('fb.1.1710000000.1234567890', $merged['fbp']);
        $this->assertSame('198.51.100.10', $merged['last_client_ip']);
        $this->assertSame('Mozilla/5.0 Storefront', $merged['last_user_agent']);
    }

    public function testApplyKeepsLiveRequestIpWhenCookiesPresent(): void
    {
        $merged = CheckoutIdentitySnapshot::applyToEventData(
            ['fbp' => 'fb.1.live'],
            [
                'last_cookies' => ['fbp' => 'fb.1.stored'],
                'last_client_ip' => '198.51.100.10',
            ]
        );
        $this->assertSame('fb.1.live', $merged['fbp']);
        $this->assertArrayNotHasKey('last_client_ip', $merged);
    }
}
