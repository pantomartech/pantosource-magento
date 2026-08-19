<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Matching-key union and cookie/IP helpers (Shopify mergeMatchingKeys / Woo 1.0.17 parity).
 */
class MatchingSignalFields
{
    /** @var list<string> */
    public const MATCHING_KEYS = [
        'fbp',
        'fbc',
        'ttp',
        'gclid',
        'fbclid',
        'ttclid',
        'msclkid',
        'gbraid',
        'wbraid',
    ];

    /** @var list<string> */
    public const COOKIE_KEYS = ['fbp', 'fbc', 'ttp'];

    public static function nonempty(mixed $value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        return trim((string) $value);
    }

    /**
     * @param array<string, mixed> $touch
     */
    public static function valueFromTouch(array $touch, string $key): string
    {
        $direct = self::nonempty($touch[$key] ?? '');
        if ($direct !== '') {
            return $direct;
        }
        $clickIds = $touch['click_ids'] ?? null;
        if (!is_array($clickIds)) {
            return '';
        }
        return self::nonempty($clickIds[$key] ?? '');
    }

    /**
     * Incoming non-empty wins; previous kept when incoming is blank.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public static function union(array $base, array $incoming): array
    {
        foreach (self::MATCHING_KEYS as $key) {
            $val = self::valueFromTouch($incoming, $key);
            if ($val !== '') {
                $base[$key] = $val;
            }
        }
        return $base;
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return array<string, string>
     */
    public static function cookiesFromSources(array $sources): array
    {
        $cookies = [];
        foreach (self::COOKIE_KEYS as $key) {
            foreach ($sources as $src) {
                if (!is_array($src)) {
                    continue;
                }
                $val = self::valueFromTouch($src, $key);
                if ($val !== '') {
                    $cookies[$key] = $val;
                    break;
                }
            }
        }
        return $cookies;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function hasLiveBrowserCookies(array $data): bool
    {
        $ctx = isset($data['context']) && is_array($data['context']) ? $data['context'] : [];
        foreach (self::COOKIE_KEYS as $key) {
            if (self::valueFromTouch($data, $key) !== '') {
                return true;
            }
            if (self::valueFromTouch($ctx, $key) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function fillFromAttribution(array $data): array
    {
        $ctx = isset($data['context']) && is_array($data['context']) ? $data['context'] : [];
        $attrib = isset($ctx['attribution']) && is_array($ctx['attribution']) ? $ctx['attribution'] : [];
        $last = isset($attrib['last_touch']) && is_array($attrib['last_touch']) ? $attrib['last_touch'] : [];
        $first = isset($attrib['first_touch']) && is_array($attrib['first_touch']) ? $attrib['first_touch'] : [];
        foreach (self::MATCHING_KEYS as $key) {
            if (self::valueFromTouch($data, $key) !== '') {
                continue;
            }
            $val = self::valueFromTouch($last, $key);
            if ($val === '') {
                $val = self::valueFromTouch($first, $key);
            }
            if ($val !== '') {
                $data[$key] = $val;
            }
        }
        return $data;
    }

    /**
     * @param list<string> $candidates
     */
    public static function firstValidIp(array $candidates): string
    {
        foreach ($candidates as $raw) {
            $ip = trim($raw);
            if ($ip === '') {
                continue;
            }
            if (str_contains($ip, ',')) {
                $ip = trim(explode(',', $ip, 2)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '';
    }

    public static function hasAnyMatching(array $incoming): bool
    {
        foreach (self::MATCHING_KEYS as $key) {
            if (self::valueFromTouch($incoming, $key) !== '') {
                return true;
            }
        }
        return false;
    }
}
