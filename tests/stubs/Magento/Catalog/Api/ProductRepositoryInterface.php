<?php
declare(strict_types=1);

namespace Magento\Catalog\Api;

use Magento\Catalog\Api\Data\ProductInterface;

interface ProductRepositoryInterface
{
    public function getById(int $productId, bool $editMode = false, ?int $storeId = null, bool $forceReload = false): ProductInterface;
}
