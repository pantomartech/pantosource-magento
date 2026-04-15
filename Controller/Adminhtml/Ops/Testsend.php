<?php
declare(strict_types=1);

namespace Panto\Source\Controller\Adminhtml\Ops;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class Testsend extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panto_Source::ops';

    public function __construct(
        Context $context,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly EventDispatcher $dispatcher,
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpRequest $httpRequest
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please try again.'));
            return $this->resultRedirectFactory->create()->setPath('pantosource/ops/index');
        }

        $storeParam = (int) $this->getRequest()->getParam('store_id', 0);
        $storeId = $storeParam > 0 ? $storeParam : (int) $this->storeManager->getDefaultStoreView()->getId();

        $data = [
            'context' => StorefrontContext::fromRequest($this->httpRequest, 'admin', 'PantoSource test'),
            'test_send' => true,
        ];

        $ok = $this->dispatcher->dispatch('pageview', $data, '', true, $storeId);
        if ($ok) {
            $this->messageManager->addSuccessMessage(
                __('Test pageview was accepted by the ingestion endpoint (or queued on failure per settings).')
            );
        } else {
            $this->messageManager->addWarningMessage(
                __('Test send did not complete successfully. Check API key, base URL, consent settings, debug timeline, or the retry queue.')
            );
        }

        return $this->resultRedirectFactory->create()->setPath('pantosource/ops/index');
    }
}
