<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class SearchObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request
    ) {
    }

    public function execute(Observer $observer): void
    {
        unset($observer);
        $q = trim((string) $this->request->getParam('q', ''));
        if ($q === '') {
            return;
        }
        $data = [
            'query' => $q,
            'result_count' => 0,
            'context' => StorefrontContext::fromRequest($this->request, 'search', 'Search results'),
        ];
        $this->dispatcher->dispatch('search_performed', $data);
    }
}
