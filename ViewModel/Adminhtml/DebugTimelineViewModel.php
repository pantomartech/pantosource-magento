<?php
declare(strict_types=1);

namespace Panto\Source\ViewModel\Adminhtml;

use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Panto\Source\Model\DebugTimeline as DebugTimelineModel;
use Panto\Source\Model\QueueBroker;

class DebugTimelineViewModel implements ArgumentInterface
{
    public function __construct(
        private readonly DebugTimelineModel $timeline,
        private readonly QueueBroker $queueBroker,
        private readonly BackendUrlInterface $backendUrl,
        private readonly FormKey $formKey,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return array{source:string,status:string,event_name:string}
     */
    public function getFilters(): array
    {
        return [
            'source' => (string) $this->request->getParam('source', ''),
            'status' => (string) $this->request->getParam('status', ''),
            'event_name' => (string) $this->request->getParam('event_name', ''),
            'attribution_source' => (string) $this->request->getParam('attribution_source', ''),
            'touch_type' => (string) $this->request->getParam('touch_type', ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRows(): array
    {
        $f = $this->getFilters();
        $safeEvent = preg_replace('/[%_]/', '', $f['event_name']);
        return $this->timeline->fetchRecentFiltered(
            400,
            $f['source'],
            $f['status'],
            $safeEvent,
            $f['attribution_source'] ?? '',
            $f['touch_type'] ?? ''
        );
    }

    public function getIndexUrl(): string
    {
        return $this->backendUrl->getUrl('pantosource/debug/index');
    }

    public function getClearUrl(): string
    {
        return $this->backendUrl->getUrl('pantosource/debug/clear');
    }

    public function getReplayUrl(): string
    {
        return $this->backendUrl->getUrl('pantosource/ops/replaydl');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDeadLetters(): array
    {
        return $this->queueBroker->fetchDeadLetters(30);
    }
}
