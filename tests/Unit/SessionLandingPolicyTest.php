<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\SessionLandingPolicy;

final class SessionLandingPolicyTest extends TestCase
{
    public function testCampaignTouchReturnsLandingUtmsAndClickIds(): void
    {
        $url = 'https://magento.test/test-product.html?utm_source=facebook&utm_campaign=m8&fbclid=FB1&ref=x';
        $this->assertSame(
            ['utm_source' => 'facebook', 'utm_campaign' => 'm8', 'fbclid' => 'FB1'],
            SessionLandingPolicy::campaignTouch($url)
        );
    }

    public function testCampaignTouchLowercasesSnapchatClickId(): void
    {
        $this->assertSame(
            ['sccid' => 'snap1'],
            SessionLandingPolicy::campaignTouch('https://magento.test/?ScCid=snap1')
        );
    }

    public function testCampaignTouchEmptyWithoutCampaignParams(): void
    {
        $this->assertSame([], SessionLandingPolicy::campaignTouch('https://magento.test/test-product.html?q=shoes'));
    }

    public function testCampaignTouchEmptyOnNonLandingPath(): void
    {
        $this->assertSame([], SessionLandingPolicy::campaignTouch('https://magento.test/checkout/cart/?gclid=G1'));
    }
}
