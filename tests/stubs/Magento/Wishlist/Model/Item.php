<?php
declare(strict_types=1);

namespace Magento\Wishlist\Model;

class Item
{
    /**
     * @param int $modelId
     * @return $this
     */
    public function load($modelId)
    {
        return $this;
    }

    public function getId(): mixed
    {
        return null;
    }

    public function getProductId(): mixed
    {
        return null;
    }

    public function getWishlistId(): mixed
    {
        return null;
    }

    public function getProduct(): mixed
    {
        return null;
    }
}
