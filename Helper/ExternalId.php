<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Canonical user.external_id. Guest Magento customer_id 0 is not a real id.
 */
class ExternalId
{
    public static function fromCustomerId(mixed $customerId): string
    {
        if ($customerId === null || $customerId === '') {
            return '';
        }
        if ((int) $customerId === 0) {
            return '';
        }
        return (string) $customerId;
    }
}
