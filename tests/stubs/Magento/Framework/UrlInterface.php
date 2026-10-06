<?php
declare(strict_types=1);

namespace Magento\Framework;

interface UrlInterface
{
    /**
     * @param string|null $routePath
     * @param array<string, mixed>|null $routeParams
     */
    public function getUrl($routePath = null, $routeParams = null);
}
