<?php
declare(strict_types=1);

namespace Magento\Store\Api\Data;

interface StoreInterface
{
    /**
     * @return int|string|null
     */
    public function getId();

    public function isUrlSecure(): bool;

    public function getCurrentCurrencyCode(): string;
}
