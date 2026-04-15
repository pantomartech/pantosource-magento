<?php
declare(strict_types=1);

namespace Panto\Source\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ConsentMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'none', 'label' => __('Always allow (development only)')],
            ['value' => 'basic', 'label' => __('Basic (block when CMP marks denied)')],
            ['value' => 'strict', 'label' => __('Strict (require pantosource_consent=granted cookie)')],
        ];
    }
}
