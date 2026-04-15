<?php
declare(strict_types=1);

namespace Panto\Source\Controller\Adminhtml\Debug;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Panto_Source::debug';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panto_Source::debug');
        $resultPage->getConfig()->getTitle()->prepend(__('PantoSource Debug Timeline'));
        return $resultPage;
    }
}
