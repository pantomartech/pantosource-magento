<?php
declare(strict_types=1);

namespace Magento\Checkout\Model;

class Session
{
    public function getQuote(): mixed
    {
        return null;
    }

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
}
