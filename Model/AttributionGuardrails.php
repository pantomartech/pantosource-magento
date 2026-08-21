<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * Blocks payment-gateway and bare self-referral last_touch overwrites (Shopify/Woo parity).
 */
class AttributionGuardrails
{
    /** @var list<string> */
    public const PAYMENT_GATEWAY_DOMAINS = [
        'paypal.com',
        'paypal-preprod.com',
        'klarna.com',
        'pay.klarna.com',
        'stripe.com',
        'checkout.stripe.com',
        'pay.stripe.com',
        'afterpay.com',
        'clearpay.co.uk',
        'sezzle.com',
        'affirm.com',
        'sandbox.affirm.com',
        'zip.co',
        'quadpay.com',
        'braintreegateway.com',
        'payments.braintree-api.com',
        'mollie.com',
        'squareup.com',
        'checkout.squareup.com',
    ];

    /** @var list<string> */
    private const CLICK_ID_KEYS = ['gclid', 'fbclid', 'ttclid', 'msclkid', 'gbraid', 'wbraid', 'dclid', 'gad_source'];

    /** @var list<string> */
    private const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign'];

    public static function stripWww(string $host): string
    {
        return strtolower(preg_replace('/^www\./i', '', trim($host)) ?? '');
    }

    public static function hostFromUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) ? self::stripWww($host) : '';
    }

    public static function isPaymentGatewayHost(string $host): bool
    {
        $bare = self::stripWww($host);
        if ($bare === '') {
            return false;
        }
        foreach (self::PAYMENT_GATEWAY_DOMAINS as $domain) {
            if ($bare === $domain || str_ends_with($bare, '.' . $domain)) {
                return true;
            }
        }
        return false;
    }

    public static function isInternalStoreReferrer(string $referrerHost, string $shopHost): bool
    {
        $ref = self::stripWww($referrerHost);
        $shop = self::stripWww($shopHost);
        return $ref !== '' && $shop !== '' && $ref === $shop;
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function shouldUpdateLastTouch(array $params, string $shopHost = ''): bool
    {
        $referrer = isset($params['referrer']) && is_string($params['referrer']) ? $params['referrer'] : '';
        $refHost = self::hostFromUrl($referrer);
        if ($refHost !== '' && self::isPaymentGatewayHost($refHost)) {
            return false;
        }
        if (self::hasClickId($params) || self::hasUtm($params)) {
            return true;
        }
        $shop = self::stripWww($shopHost);
        if ($refHost !== '' && $shop !== '' && self::isInternalStoreReferrer($refHost, $shop)) {
            return false;
        }
        if ($refHost !== '' && $shop !== '' && $refHost !== $shop) {
            return true;
        }
        foreach ($params as $key => $value) {
            if ($key === 'referrer' || $key === 'shopHost') {
                continue;
            }
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function hasClickId(array $params): bool
    {
        foreach (self::CLICK_ID_KEYS as $key) {
            if (isset($params[$key]) && is_string($params[$key]) && trim($params[$key]) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function hasUtm(array $params): bool
    {
        foreach (self::UTM_KEYS as $key) {
            if (isset($params[$key]) && is_string($params[$key]) && trim($params[$key]) !== '') {
                return true;
            }
        }
        return false;
    }
}
