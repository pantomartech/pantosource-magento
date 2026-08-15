<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;

/**
 * Storefront-only gate for customer lifecycle observers (skip adminhtml / webapi).
 */
class FrontendArea
{
    public function __construct(
        private readonly State $appState
    ) {
    }

    public function isFrontend(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_FRONTEND;
        } catch (\Throwable) {
            return false;
        }
    }
}
