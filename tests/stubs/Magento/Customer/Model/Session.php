<?php
declare(strict_types=1);

namespace Magento\Customer\Model;

class Session
{
    /**
     * @param string $key
     * @return mixed
     */
    public function getData($key)
    {
        return null;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setData($key, $value)
    {
        return $this;
    }

    public function isLoggedIn(): bool
    {
        return false;
    }

    public function getCustomerId(): mixed
    {
        return null;
    }
}
