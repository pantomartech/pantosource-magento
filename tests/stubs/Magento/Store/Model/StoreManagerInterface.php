<?php
declare(strict_types=1);

namespace Magento\Store\Model;

interface StoreManagerInterface
{
    /**
     * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
     * @return mixed
     */
    public function getStore($storeId = null);
}
