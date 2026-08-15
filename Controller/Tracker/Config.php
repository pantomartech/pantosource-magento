<?php
declare(strict_types=1);

namespace Panto\Source\Controller\Tracker;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Model\FrontendTrackerConfig;

class Config extends Action implements HttpGetActionInterface
{
    private const CACHE_CONTROL_HEADER = 'no-store, no-cache, must-revalidate, max-age=0';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FrontendTrackerConfig $frontendTrackerConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', self::CACHE_CONTROL_HEADER, true);

        $storeId = (int) $this->storeManager->getStore()->getId();
        $pagePath = (string) $this->getRequest()->getParam('page_path', '');
        $config = $this->frontendTrackerConfig->build($storeId, $pagePath);
        if ($config === null) {
            return $result->setHttpResponseCode(204);
        }

        return $result->setData($config);
    }
}
