<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Framework\App\Request\Http as HttpRequest;
use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\StorefrontContext;

final class PurchaseConversionContextTest extends TestCase
{
    public function testPurchasePageIsCheckoutSuccessWhenRequestIsInvoiceAdmin(): void
    {
        $request = new class extends HttpRequest {
            public function getRequestUri(): string
            {
                return '/admin/sales/order_invoice/save/order_id/42/';
            }
        };

        $fromRequest = StorefrontContext::fromRequest($request, 'checkout', 'Success');
        $this->assertSame('/admin/sales/order_invoice/save/order_id/42/', $fromRequest['page']['path']);
        $this->assertSame('', $fromRequest['page']['url']);

        $stamped = StorefrontContext::withCheckoutSuccessPage(
            $fromRequest,
            'https://magento.test/checkout/onepage/success/'
        );
        $this->assertSame('https://magento.test/checkout/onepage/success/', $stamped['page']['url']);
        $this->assertSame('/checkout/onepage/success/', $stamped['page']['path']);
        $this->assertSame('/checkout/onepage/success/', $stamped['path']);
        $this->assertStringNotContainsString('/admin/', (string) $stamped['page']['path']);
    }
}
