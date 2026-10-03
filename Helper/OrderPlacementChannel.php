<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Online-store classification for Magento order placement areas (store-health CH-1 / D-13).
 */
final class OrderPlacementChannel
{
    public const FRONTEND = 'frontend';
    public const GRAPHQL = 'graphql';
    public const WEBAPI_REST = 'webapi_rest';

    public static function isOnlineStoreChannel(string $salesChannel): bool
    {
        return in_array($salesChannel, [self::FRONTEND, self::GRAPHQL, self::WEBAPI_REST], true);
    }

    /**
     * Whether to persist sales_channel on pantosource_order_identity at placement time.
     */
    public static function shouldRecordAtPlacement(string $areaCode, bool $webapiHasCartToken): bool
    {
        if ($areaCode === self::FRONTEND || $areaCode === self::GRAPHQL) {
            return true;
        }
        if ($areaCode === self::WEBAPI_REST) {
            return $webapiHasCartToken;
        }

        return false;
    }

    /**
     * @return array{0: string, 1: bool} channel, is_online_store
     */
    public static function snapshotChannel(?string $storedSalesChannel): array
    {
        $channel = $storedSalesChannel !== null ? trim($storedSalesChannel) : '';
        if ($channel === '') {
            return ['', false];
        }

        return [$channel, self::isOnlineStoreChannel($channel)];
    }
}
