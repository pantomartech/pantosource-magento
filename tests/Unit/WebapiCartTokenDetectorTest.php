<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\WebapiCartTokenDetector;

final class WebapiCartTokenDetectorTest extends TestCase
{
    public function testGuestQuoteHeader(): void
    {
        $this->assertTrue(WebapiCartTokenDetector::hasGuestOrCustomerCartContext('/rest/V1/orders', [
            'HTTP_X_MAGENTO_GUEST_QUOTE_ID' => 'abc123',
        ]));
    }

    public function testGuestCartsUri(): void
    {
        $this->assertTrue(WebapiCartTokenDetector::hasGuestOrCustomerCartContext(
            '/rest/default/V1/guest-carts/abc/order',
            []
        ));
    }

    public function testIntegrationOrderUriWithoutCartIsFalse(): void
    {
        $this->assertFalse(WebapiCartTokenDetector::hasGuestOrCustomerCartContext(
            '/rest/V1/orders',
            []
        ));
    }
}
