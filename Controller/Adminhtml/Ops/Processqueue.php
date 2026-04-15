<?php
declare(strict_types=1);

namespace Panto\Source\Controller\Adminhtml\Ops;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Panto\Source\Cron\ProcessQueue as ProcessQueueCron;

class Processqueue extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panto_Source::ops';

    public function __construct(
        Context $context,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly ProcessQueueCron $processQueue
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please try again.'));
            return $this->resultRedirectFactory->create()->setPath('pantosource/ops/index');
        }

        $this->processQueue->execute();
        $this->messageManager->addSuccessMessage(__('Processed one batch from the outbound retry queue (same logic as cron).'));

        return $this->resultRedirectFactory->create()->setPath('pantosource/ops/index');
    }
}
