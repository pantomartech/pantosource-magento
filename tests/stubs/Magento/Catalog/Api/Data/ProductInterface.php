<?php
declare(strict_types=1);

namespace Magento\Catalog\Api\Data;

interface ProductInterface
{
    /**
     * @return int|string|null
     */
    public function getId();

    public function getName(): ?string;

    public function getSku(): ?string;

    /**
     * @return float|string|null
     */
    public function getPrice();
}
