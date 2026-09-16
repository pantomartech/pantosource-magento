<?php
declare(strict_types=1);

namespace Panto\Source\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Model\TrackingVerifyMarker;

/**
 * Non-cacheable head marker so API key rotation is not stuck in FPC.
 */
class HeadMarker extends Template
{
    public function __construct(
        Context $context,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly TrackingVerifyMarker $trackingVerifyMarker,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getMarkerHtml(): string
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return '';
        }

        return $this->trackingVerifyMarker->build($this->config->getApiKey($storeId));
    }
}
