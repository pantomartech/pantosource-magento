<?php
declare(strict_types=1);

namespace Panto\Source\Test\Unit;

use Magento\Framework\App\Request\Http as HttpRequest;
use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\CheckoutEmailIdentify;

final class CheckoutEmailIdentifyTest extends TestCase
{
    public function testGuestQuoteDoesNotSkip(): void
    {
        $quote = new class {
            public function getCustomerId(): int
            {
                return 0;
            }
        };
        $this->assertFalse(CheckoutEmailIdentify::quoteHasLoggedInCustomer($quote));
    }

    public function testLoggedInQuoteSkips(): void
    {
        $quote = new class {
            public function getCustomerId(): int
            {
                return 42;
            }
        };
        $this->assertTrue(CheckoutEmailIdentify::quoteHasLoggedInCustomer($quote));
    }

    public function testPaypalPathSuppressesCustomerUpdated(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestUri')->willReturn('/paypal/express/return/');
        $this->assertTrue(CheckoutEmailIdentify::shouldSkipCustomerUpdated($request));
    }

    public function testAccountPathAllowsCustomerUpdated(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestUri')->willReturn('/customer/account/edit/');
        $request->method('isXmlHttpRequest')->willReturn(false);
        $this->assertFalse(CheckoutEmailIdentify::shouldSkipCustomerUpdated($request));
    }

    public function testGraphqlPathSuppressesCustomerUpdated(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestUri')->willReturn('/graphql');
        $request->method('isXmlHttpRequest')->willReturn(false);
        $this->assertTrue(CheckoutEmailIdentify::shouldSkipCustomerUpdated($request));
    }

    public function testIpnPathSuppressesCustomerUpdated(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestUri')->willReturn('/paypal/standard/ipn/');
        $request->method('isXmlHttpRequest')->willReturn(false);
        $this->assertTrue(CheckoutEmailIdentify::shouldSkipCustomerUpdated($request));
    }

    public function testAjaxRequestSuppressesCustomerUpdated(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestUri')->willReturn('/customer/account/edit/');
        $request->method('isXmlHttpRequest')->willReturn(true);
        $this->assertTrue(CheckoutEmailIdentify::shouldSkipCustomerUpdated($request));
    }
}
