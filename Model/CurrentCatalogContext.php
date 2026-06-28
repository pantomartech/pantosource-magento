<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Registry;

/**
 * Encapsulates deprecated Registry reads for current catalog entities.
 */
class CurrentCatalogContext
{
    public function __construct(
        private readonly Registry $registry
    ) {
    }

    public function getCurrentProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        if ($product instanceof ProductInterface && $product->getId()) {
            return $product;
        }

        return null;
    }

    public function getCurrentCategory(): ?CategoryInterface
    {
        $category = $this->registry->registry('current_category');
        if ($category instanceof CategoryInterface && $category->getId()) {
            return $category;
        }

        return null;
    }
}
