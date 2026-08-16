<?php
declare(strict_types=1);

namespace Magento\Catalog\Api;

interface CategoryRepositoryInterface
{
    /**
     * @param int $categoryId
     * @param int|null $storeId
     * @return mixed
     */
    public function get($categoryId, $storeId = null);
}
