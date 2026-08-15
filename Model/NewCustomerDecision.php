<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * Shopify analogue: new when platform order count is finite and <= 1 (includes the current order).
 */
class NewCustomerDecision
{
    public static function isNew(int $customerId, int $registeredOrderCount, int $guestEmailOrderCount): bool
    {
        if ($customerId > 0) {
            return $registeredOrderCount <= 1;
        }
        return $guestEmailOrderCount <= 1;
    }
}
