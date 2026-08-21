<?php
declare(strict_types=1);

namespace Magento\Directory\Model;

class CountryFactory
{
    public function create(): Country
    {
        return new Country();
    }
}
