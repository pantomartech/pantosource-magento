<?php
declare(strict_types=1);

namespace Magento\Framework\App\Config;

interface ScopeConfigInterface
{
    /**
     * @param string $path
     * @param string $scopeType
     * @param mixed $scopeCode
     * @return mixed
     */
    public function getValue($path, $scopeType = 'default', $scopeCode = null);

    /**
     * @param string $path
     * @param string $scopeType
     * @param mixed $scopeCode
     */
    public function isSetFlag($path, $scopeType = 'default', $scopeCode = null): bool;
}
