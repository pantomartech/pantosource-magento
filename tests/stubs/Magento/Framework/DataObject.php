<?php
declare(strict_types=1);

namespace Magento\Framework;

class DataObject
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        protected array $_data = []
    ) {
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getData($key = '', $index = null)
    {
        unset($index);
        if ($key === '') {
            return $this->_data;
        }
        return $this->_data[$key] ?? null;
    }
}
