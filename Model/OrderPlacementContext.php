<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State;
use Panto\Source\Helper\OrderPlacementChannel;
use Panto\Source\Helper\WebapiCartTokenDetector;

/**
 * Resolves Magento area + webapi cart context at order placement (D-13).
 */
class OrderPlacementContext
{
    public function __construct(
        private readonly State $appState,
        private readonly HttpRequest $httpRequest
    ) {
    }

    /**
     * sales_channel to persist, or null when the order is not an online-store placement.
     */
    public function resolveSalesChannelForPlacement(): ?string
    {
        try {
            $area = (string) $this->appState->getAreaCode();
        } catch (\Throwable) {
            return null;
        }

        $hasCart = WebapiCartTokenDetector::hasGuestOrCustomerCartContext(
            (string) $this->httpRequest->getRequestUri(),
            $_SERVER
        );
        if (!OrderPlacementChannel::shouldRecordAtPlacement($area, $hasCart)) {
            return null;
        }

        return $area;
    }
}
