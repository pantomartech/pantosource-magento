<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Guest checkout-email identify only; skip logged-in quotes and payment return paths.
 */
final class CheckoutEmailIdentify
{
    public static function quoteHasLoggedInCustomer(mixed $quote): bool
    {
        if ($quote === null) {
            return false;
        }
        try {
            if (method_exists($quote, 'getCustomerId')) {
                return (int) $quote->getCustomerId() > 0;
            }
        } catch (\Throwable) {
            return false;
        }
        return false;
    }

    public static function shouldSkipCustomerUpdated(HttpRequest $request): bool
    {
        try {
            if (method_exists($request, 'isXmlHttpRequest') && $request->isXmlHttpRequest()) {
                return true;
            }
            if (method_exists($request, 'isAjax') && $request->isAjax()) {
                return true;
            }
        } catch (\Throwable) {
        }
        $path = strtolower((string) $request->getRequestUri());
        return str_contains($path, '/checkout')
            || str_contains($path, 'paypal')
            || str_contains($path, '/paypal/')
            || str_contains($path, 'ipn')
            || str_contains($path, '/graphql')
            || str_contains($path, '/rest/')
            || str_contains($path, '/v1/guest-carts')
            || str_contains($path, '/v1/carts');
    }
}
