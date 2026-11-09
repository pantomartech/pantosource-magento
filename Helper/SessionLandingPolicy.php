<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * When a storefront page replaces the session landing URL (ATR-16, WooCommerce parity).
 *
 * A page that arrives with UTMs or click ids is a new campaign landing, unless it cannot be a
 * landing (cart, checkout, success, customer-data and API endpoints). Mirrored in tracker.js
 * (`resolveSessionLanding`).
 */
final class SessionLandingPolicy
{
    /** @var list<string> */
    public const CAMPAIGN_PARAMS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'ttclid',
        'msclkid',
        'twclid',
        'epik',
        'sccid',
        'ScCid',
        'oppref',
        'gbraid',
        'wbraid',
        'dclid',
        'gad_source',
    ];

    /** @var list<string> */
    private const NON_LANDING_PATH_PREFIXES = [
        '/checkout',
        '/customer/section',
        '/pantosource/',
        '/rest/',
        '/graphql',
        '/static/',
        '/media/',
    ];

    /**
     * @param array<string, mixed> $query
     */
    public static function hasCampaignParams(array $query): bool
    {
        foreach (self::CAMPAIGN_PARAMS as $key) {
            if (isset($query[$key]) && is_string($query[$key]) && $query[$key] !== '') {
                return true;
            }
        }
        return false;
    }

    public static function isNonLandingPath(string $path): bool
    {
        $lower = strtolower($path);
        foreach (self::NON_LANDING_PATH_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The URL itself when it is a new campaign landing, else ''.
     */
    public static function freshCampaignLanding(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['query'])) {
            return '';
        }
        parse_str((string) $parts['query'], $query);
        if (!self::hasCampaignParams($query)) {
            return '';
        }
        if (self::isNonLandingPath((string) ($parts['path'] ?? '/'))) {
            return '';
        }
        return $url;
    }

    /**
     * UTMs and click ids of a campaign landing URL, keyed as in the attribution cookie (`ScCid` →
     * `sccid`); [] when the URL is not a new campaign landing.
     *
     * @return array<string, string>
     */
    public static function campaignTouch(string $url): array
    {
        if (self::freshCampaignLanding($url) === '') {
            return [];
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $touch = [];
        foreach (self::CAMPAIGN_PARAMS as $key) {
            $value = $query[$key] ?? null;
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $touch[strtolower($key)] = trim($value);
        }
        return $touch;
    }
}
