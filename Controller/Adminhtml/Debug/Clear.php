<?php
declare(strict_types=1);

namespace Panto\Source\Controller\Adminhtml\Debug;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Panto\Source\Model\DebugTimeline;

class Clear extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panto_Source::debug';

    public function __construct(
        Context $context,
        private readonly DebugTimeline $debugTimeline
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $this->debugTimeline->clear();
        $this->messageManager->addSuccessMessage(__('PantoSource debug timeline cleared.'));
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
