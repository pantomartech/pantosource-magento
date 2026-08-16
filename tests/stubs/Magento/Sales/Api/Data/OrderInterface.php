<?php
declare(strict_types=1);

namespace Magento\Sales\Api\Data;

interface OrderInterface
{
    /**
     * @return mixed
     */
    public function getStoreId();

    /**
     * @return mixed
     */
    public function getCustomerEmail();

    /**
     * @return mixed
     */
    public function getBillingAddress();
}
