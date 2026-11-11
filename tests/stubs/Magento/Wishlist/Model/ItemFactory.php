<?php
declare(strict_types=1);

namespace Magento\Wishlist\Model;

class ItemFactory
{
    public function create(): Item
    {
        return new Item();
    }
}
