<?php
declare(strict_types=1);

namespace Panto\Source\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;

/**
 * Cacheable storefront shell; session-specific tracker config is loaded via GET pantosource/tracker/config.
 */
class Tracker extends Template
{
    public function __construct(
        Context $context,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId) || !$this->config->isFrontendTrackerEnabled($storeId)) {
            return '';
        }

        return parent::_toHtml();
    }

    public function getConfigUrl(): string
    {
        $store = $this->storeManager->getStore();

        return $this->getUrl('pantosource/tracker/config', ['_secure' => $store->isUrlSecure()]);
    }
}
