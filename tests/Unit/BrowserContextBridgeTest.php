<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Framework\App\Request\Http as HttpRequest;
use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\BrowserContextBridge;
use Panto\Source\Helper\SessionLandingPolicy;

final class BrowserContextBridgeTest extends TestCase
{
    private const GOOGLE_LANDING = 'https://magento.test/?utm_source=google&utm_campaign=m1';

    public function testCookieNameConstant(): void
    {
        $this->assertSame('pantosource_browser_ctx', BrowserContextBridge::COOKIE_NAME);
    }

    public function testCookieLandingKeptWithoutCampaignParams(): void
    {
        $request = $this->requestWithCookieLanding('https://magento.test/test-product.html');
        $data = (new BrowserContextBridge($request))->mergeIntoData([]);
        $this->assertSame(self::GOOGLE_LANDING, $data['context']['session']['landing_url']);
    }

    public function testCampaignRequestReplacesCookieLanding(): void
    {
        $url = 'https://magento.test/test-product.html?utm_source=facebook&fbclid=abc';
        $request = $this->requestWithCookieLanding($url);
        $data = (new BrowserContextBridge($request))->mergeIntoData([]);
        $this->assertSame($url, $data['context']['session']['landing_url']);
    }

    public function testCampaignRequestSetsLandingWithoutCookie(): void
    {
        $request = new HttpRequest();
        $request->uriString = 'https://magento.test/?gclid=xyz';
        $data = (new BrowserContextBridge($request))->mergeIntoData([]);
        $this->assertSame('https://magento.test/?gclid=xyz', $data['context']['session']['landing_url']);
    }

    public function testAjaxRequestKeepsCookieLanding(): void
    {
        $request = $this->requestWithCookieLanding('https://magento.test/?utm_source=facebook');
        $request->ajax = true;
        $data = (new BrowserContextBridge($request))->mergeIntoData([]);
        $this->assertSame(self::GOOGLE_LANDING, $data['context']['session']['landing_url']);
    }

    public function testCheckoutIsNeverALanding(): void
    {
        $this->assertSame('', SessionLandingPolicy::freshCampaignLanding('https://magento.test/checkout/onepage/success/?utm_source=x'));
        $this->assertSame('', SessionLandingPolicy::freshCampaignLanding('https://magento.test/checkout/cart/?fbclid=x'));
        $this->assertSame('', SessionLandingPolicy::freshCampaignLanding('https://magento.test/test-product.html'));
        $this->assertTrue(SessionLandingPolicy::hasCampaignParams(['ScCid' => 'snap']));
    }

    private function requestWithCookieLanding(string $currentUrl): HttpRequest
    {
        $request = new HttpRequest();
        $request->uriString = $currentUrl;
        $request->cookies[BrowserContextBridge::COOKIE_NAME] = (string) json_encode(
            ['session' => ['landing_url' => self::GOOGLE_LANDING]]
        );
        return $request;
    }
}
