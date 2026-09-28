<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * Builds marketing.attribution.meta (Woo/Shopify parity).
 */
class AttributionMetaBuilder
{
    public const ATTRIBUTION_LOOKBACK_DAYS = 30;

    /** @var list<string> */
    private const CLICK_ID_KEYS = [
        'gclid',
        'fbclid',
        'ttclid',
        'msclkid',
        'twclid',
        'epik',
        'sccid',
        'oppref',
        'gbraid',
        'wbraid',
        'dclid',
        'gad_source',
    ];

    /**
     * @param array<string, mixed> $touch
     */
    public static function inferAttributionSource(array $touch): string
    {
        foreach (self::CLICK_ID_KEYS as $key) {
            if (self::touchHasString($touch, $key)) {
                return 'click_id';
            }
        }
        if (
            self::touchHasString($touch, 'utm_source')
            || self::touchHasString($touch, 'utm_medium')
            || self::touchHasString($touch, 'utm_campaign')
        ) {
            return 'utm';
        }
        if (self::touchHasString($touch, 'referrer')) {
            return 'referrer';
        }
        return 'none';
    }

    /**
     * @param array<string, mixed> $firstTouch
     * @param array<string, mixed> $lastTouch
     * @param array<string, mixed> $attribContext
     * @return array<string, mixed>|null
     */
    public static function buildAttributionMeta(
        array $firstTouch,
        array $lastTouch,
        array $attribContext = [],
        ?int $lookbackDays = null
    ): ?array {
        if (self::touchIsEmpty($firstTouch) && self::touchIsEmpty($lastTouch)) {
            return null;
        }

        $meta = [];
        $inferred = self::inferAttributionSource($lastTouch);
        if ($inferred !== 'none') {
            $meta['attribution_source'] = $inferred;
        } elseif (
            isset($attribContext['attribution_source'])
            && is_string($attribContext['attribution_source'])
            && trim($attribContext['attribution_source']) !== ''
            && $attribContext['attribution_source'] !== 'none'
        ) {
            $meta['attribution_source'] = trim($attribContext['attribution_source']);
        }

        $capturedAt = '';
        if (isset($attribContext['captured_at']) && is_string($attribContext['captured_at'])) {
            $capturedAt = trim($attribContext['captured_at']);
        }
        if ($capturedAt === '' && isset($lastTouch['captured_at']) && is_string($lastTouch['captured_at'])) {
            $capturedAt = trim($lastTouch['captured_at']);
        }
        if ($capturedAt === '' && isset($firstTouch['captured_at']) && is_string($firstTouch['captured_at'])) {
            $capturedAt = trim($firstTouch['captured_at']);
        }
        if ($capturedAt === '') {
            $capturedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
        }
        $meta['captured_at'] = $capturedAt;

        $lookback = $lookbackDays ?? self::ATTRIBUTION_LOOKBACK_DAYS;
        if (isset($attribContext['lookback_days']) && is_numeric($attribContext['lookback_days'])) {
            $lookback = (int) $attribContext['lookback_days'];
        }
        $meta['lookback_days'] = max(1, $lookback);

        return $meta;
    }

    /**
     * @param array<string, mixed> $touch
     */
    private static function touchHasString(array $touch, string $key): bool
    {
        return isset($touch[$key]) && is_string($touch[$key]) && trim($touch[$key]) !== '';
    }

    /**
     * @param array<string, mixed> $touch
     */
    private static function touchIsEmpty(array $touch): bool
    {
        return $touch === [];
    }
}
