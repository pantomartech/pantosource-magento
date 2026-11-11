<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Normalises the numeric fields of magento_page_engagement posted by the storefront tracker.
 * Values come from the browser, so they are cast to int and clamped; a non-numeric value is dropped.
 */
class PageEngagementFields
{
    public const EVENT_NAME = 'magento_page_engagement';
    public const MAX_ENGAGED_MS = 86400000;
    public const MAX_SCROLL_PCT = 100;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function sanitize(array $data): array
    {
        $data = self::clampKey($data, 'engaged_ms', self::MAX_ENGAGED_MS);
        return self::clampKey($data, 'max_scroll_pct', self::MAX_SCROLL_PCT);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function clampKey(array $data, string $key, int $max): array
    {
        if (!array_key_exists($key, $data)) {
            return $data;
        }
        if (!is_numeric($data[$key])) {
            unset($data[$key]);
            return $data;
        }
        $data[$key] = (int) round(max(0.0, min((float) $max, (float) $data[$key])));
        return $data;
    }
}
