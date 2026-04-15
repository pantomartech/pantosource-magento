<?php
declare(strict_types=1);

namespace Panto\Source\ViewModel\Adminhtml;

use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Model\QueueBroker;

class OpsDashboardViewModel implements ArgumentInterface
{
    public function __construct(
        private readonly QueueBroker $queueBroker,
        private readonly BackendUrlInterface $backendUrl,
        private readonly FormKey $formKey,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getPendingCount(): int
    {
        return $this->queueBroker->getPendingCount();
    }

    public function getDeadLetterCount(): int
    {
        return $this->queueBroker->getDeadLetterCount();
    }

    /**
     * @return list<array{surface:string,public_id:string,store_id:int,last_error:string,created_at:string}>
     */
    public function getRecentErrors(): array
    {
        return $this->queueBroker->fetchRecentErrorSamples(10);
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public function getStoreOptions(): array
    {
        $out = [['value' => '0', 'label' => (string) __('Default store view')]];
        foreach ($this->storeManager->getStores() as $store) {
            $out[] = [
                'value' => (string) (int) $store->getId(),
                'label' => (string) $store->getName() . ' [' . $store->getCode() . ']',
            ];
        }
        return $out;
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getTestSendUrl(): string
    {
        return $this->backendUrl->getUrl('pantosource/ops/testsend');
    }

    public function getReplayUrl(): string
    {
        return $this->backendUrl->getUrl('pantosource/ops/replaydl');
    }

    public function getProcessQueueUrl(): string
    {
        return $this->backendUrl->getUrl('pantosource/ops/processqueue');
    }

    public function getDebugUrl(): string
    {
        return $this->backendUrl->getUrl('pantosource/debug/index');
    }
}
