<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Quote-scoped checkout_initiated dedupe key (legacy unscoped key is not reused).
 */
class CheckoutInitSessionKey
{
    public const PREFIX = 'pantosource_checkout_init_sent_';

    public static function forQuote(int $quoteId): string
    {
        return self::PREFIX . $quoteId;
    }
}
