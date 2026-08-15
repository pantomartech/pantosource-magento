<?php
declare(strict_types=1);

namespace Magento\UrlRewrite\Model;

interface UrlFinderInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function findOneByData(array $data);

    /**
     * @param array<string, mixed> $data
     * @return array<int, mixed>
     */
    public function findAllByData(array $data);
}
