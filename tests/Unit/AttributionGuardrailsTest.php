<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\AttributionGuardrails;

final class AttributionGuardrailsTest extends TestCase
{
    public function testPayPalReferrerBlocksLastTouchUpdate(): void
    {
        $ok = AttributionGuardrails::shouldUpdateLastTouch(
            ['referrer' => 'https://www.paypal.com/checkout'],
            'example.com'
        );
        $this->assertFalse($ok);
    }

    public function testStripeReferrerBlocksLastTouchUpdate(): void
    {
        $ok = AttributionGuardrails::shouldUpdateLastTouch(
            ['referrer' => 'https://checkout.stripe.com/pay/cs_test'],
            'example.com'
        );
        $this->assertFalse($ok);
    }

    public function testUtmStillUpdatesDespiteGatewayReferrer(): void
    {
        $ok = AttributionGuardrails::shouldUpdateLastTouch(
            [
                'referrer' => 'https://checkout.stripe.com/pay/cs_test',
                'utm_source' => 'google',
            ],
            'example.com'
        );
        $this->assertTrue($ok);
    }

    public function testSelfReferrerWithoutUtmBlocked(): void
    {
        $ok = AttributionGuardrails::shouldUpdateLastTouch(
            ['referrer' => 'https://example.com/cart'],
            'example.com'
        );
        $this->assertFalse($ok);
    }
}
