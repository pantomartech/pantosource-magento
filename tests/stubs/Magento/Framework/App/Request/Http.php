<?php
declare(strict_types=1);

namespace Magento\Framework\App\Request;

class Http
{
    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getParam($key, $default = null)
    {
        return $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setParam($key, $value)
    {
        return $this;
    }

    public function getRequestUri(): string
    {
        return '';
    }

    public function isXmlHttpRequest(): bool
    {
        return false;
    }

    public function isAjax(): bool
    {
        return false;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getServer($key, $default = null)
    {
        return $default;
    }
}
