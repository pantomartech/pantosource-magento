<?php
declare(strict_types=1);

namespace Magento\Quote\Api;

interface GuestCartItemRepositoryInterface
{
    /**
     * @param mixed $cartItem
     * @return mixed
     */
    public function save($cartItem);
}
