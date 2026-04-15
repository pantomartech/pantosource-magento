<?php
declare(strict_types=1);

namespace Panto\Source\Controller\Adminhtml\Ops;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Panto_Source::ops';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panto_Source::ops');
        $resultPage->getConfig()->getTitle()->prepend(__('PantoSource Operations'));
        return $resultPage;
    }
}
