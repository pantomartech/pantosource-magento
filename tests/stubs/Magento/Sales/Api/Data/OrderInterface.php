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

    /**
     * @return mixed
     */
    public function getShippingAddress();

    /**
     * @return mixed
     */
    public function getCustomerFirstname();

    /**
     * @return mixed
     */
    public function getCustomerLastname();

    /**
     * @return mixed
     */
    public function getCustomerId();

    /**
     * @return mixed
     */
    public function getId();

    /**
     * @return mixed
     */
    public function getGrandTotal();

    /**
     * @return mixed
     */
    public function getOrderCurrencyCode();

    /**
     * @return mixed
     */
    public function getStatus();

    /**
     * @param string $key
     * @return mixed
     */
    public function getData($key = '');

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function setData($key, $value = null);
}
