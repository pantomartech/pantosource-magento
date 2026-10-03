<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Detect guest/customer cart checkout on Magento webapi_rest (D-13).
 */
final class WebapiCartTokenDetector
{
    /**
     * @param array<string, mixed> $serverParams $_SERVER-style map (HTTP_* keys)
     */
    public static function hasGuestOrCustomerCartContext(string $requestUri, array $serverParams): bool
    {
        if (self::headerValue($serverParams, 'HTTP_X_MAGENTO_GUEST_QUOTE_ID') !== '') {
            return true;
        }

        $uri = strtolower($requestUri);
        if (str_contains($uri, '/guest-carts/') || str_contains($uri, '/carts/mine')) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $serverParams
     */
    private static function headerValue(array $serverParams, string $key): string
    {
        foreach ($serverParams as $name => $value) {
            if (strtoupper((string) $name) === $key) {
                return trim((string) $value);
            }
        }

        return '';
    }
}
