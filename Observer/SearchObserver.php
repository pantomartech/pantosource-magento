<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Search\Model\QueryFactory;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;
use Magento\Store\Model\StoreManagerInterface;

class SearchObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly QueryFactory $queryFactory,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        unset($observer);
        $storeId = (int) $this->storeManager->getStore()->getId();
        if ($this->config->isFrontendTrackerEnabled($storeId)) {
            return;
        }
        $q = trim((string) $this->request->getParam('q', ''));
        if ($q === '') {
            return;
        }
        $resultCount = 0;
        try {
            $query = $this->queryFactory->get();
            if ($query && method_exists($query, 'getNumResults')) {
                $raw = $query->getNumResults();
                if ($raw !== null) {
                    $resultCount = (int) $raw;
                }
            }
        } catch (\Throwable) {
            $resultCount = 0;
        }
        $data = [
            'query' => $q,
            'result_count' => $resultCount,
            'context' => StorefrontContext::fromRequest($this->request, 'search', 'Search results'),
        ];
        $this->dispatcher->dispatch('search_performed', $data);
    }
}
