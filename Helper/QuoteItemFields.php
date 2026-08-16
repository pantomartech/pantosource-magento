<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Read Magento quote-item fields. Quote\Item uses DataObject __call for
 * getProductId/getQty/getName — method_exists() is false on those getters.
 */
class QuoteItemFields
{
    public static function int(mixed $item, string $dataKey, string $magicGetter): int
    {
        return (int) (self::raw($item, $dataKey, $magicGetter) ?? 0);
    }

    public static function float(mixed $item, string $dataKey, string $magicGetter): float
    {
        return (float) (self::raw($item, $dataKey, $magicGetter) ?? 0);
    }

    public static function string(mixed $item, string $dataKey, string $magicGetter): string
    {
        return (string) (self::raw($item, $dataKey, $magicGetter) ?? '');
    }

    public static function origFloat(mixed $item, string $key): ?float
    {
        if (!is_object($item) || !method_exists($item, 'getOrigData')) {
            return null;
        }
        $raw = $item->getOrigData($key);
        if ($raw === null || $raw === '') {
            return null;
        }
        return (float) $raw;
    }

    private static function raw(mixed $item, string $dataKey, string $magicGetter): mixed
    {
        if (!is_object($item)) {
            return null;
        }
        if (method_exists($item, 'getData')) {
            try {
                $viaData = $item->getData($dataKey);
                if ($viaData !== null && $viaData !== '') {
                    return $viaData;
                }
            } catch (\Throwable) {
            }
        }
        try {
            if (is_callable([$item, $magicGetter])) {
                return $item->{$magicGetter}();
            }
        } catch (\Throwable) {
        }
        return null;
    }
}
