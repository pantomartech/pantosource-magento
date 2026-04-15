<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Registry;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CategoryViewObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly Registry $registry
    ) {
    }

    public function execute(Observer $observer): void
    {
        unset($observer);
        $category = $this->registry->registry('current_category');
        if (!$category || !$category->getId()) {
            return;
        }
        $data = [
            'list_type' => 'category',
            'category_id' => (int) $category->getId(),
            'category_name' => (string) $category->getName(),
            'context' => StorefrontContext::fromRequest(
                $this->request,
                'category',
                (string) $category->getName()
            ),
        ];
        $this->dispatcher->dispatch('product_list_viewed', $data);
    }
}
