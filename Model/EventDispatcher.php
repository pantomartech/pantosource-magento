<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Psr\Log\LoggerInterface;

use Panto\Source\Model\ConversionAttributionSnapshot;

class EventDispatcher
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly PayloadBuilder $payloadBuilder,
        private readonly IngestionClient $ingestionClient,
        private readonly QueueBroker $queueBroker,
        private readonly DebugTimeline $debugTimeline,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly ConversionAttributionSnapshot $conversionSnapshot
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function dispatch(
        string $eventName,
        array $data = [],
        string $eventTypeOverride = '',
        bool $enqueueOnError = true,
        ?int $storeId = null
    ): bool {
        $sid = $storeId ?? (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($sid)) {
            return false;
        }
        if (!$this->config->hasTrackingConsent($sid)) {
            return false;
        }

        $missing = EventRegistry::getMissingRequired($eventName, $data);
        if ($missing !== []) {
            $this->logger->info('PantoSource event skipped: missing keys', ['event' => $eventName, 'missing' => $missing]);
            return false;
        }

        if ($this->conversionSnapshot->appliesTo($eventName)) {
            $data = $this->conversionSnapshot->attach($data, $sid);
        }

        try {
            $payload = $this->payloadBuilder->build($eventName, $data, $eventTypeOverride, $sid);
        } catch (\Throwable $e) {
            $this->logger->error('PantoSource payload build failed: ' . $e->getMessage());
            return false;
        }

        $this->debugTimeline->append([
            'source' => 'dispatch',
            'hook_or_action' => 'attempt',
            'event_name' => $eventName,
            'event_id' => (string) ($payload['event']['id'] ?? ''),
            'status' => 'attempted',
            'context' => ['store_id' => $sid],
            'payload' => $payload,
        ], $sid);

        [$ok, $code, $msg] = $this->ingestionClient->send($payload, true, $sid);
        if ($ok) {
            $this->debugTimeline->append([
                'source' => 'dispatch',
                'hook_or_action' => 'success',
                'event_name' => $eventName,
                'event_id' => (string) ($payload['event']['id'] ?? ''),
                'status' => 'success',
                'context' => ['http' => $code],
                'payload' => $payload,
            ], $sid);
            return true;
        }

        $this->debugTimeline->append([
            'source' => 'dispatch',
            'hook_or_action' => 'failed',
            'event_name' => $eventName,
            'event_id' => (string) ($payload['event']['id'] ?? ''),
            'status' => 'failed',
            'context' => ['http' => $code, 'error' => substr($msg, 0, 500)],
            'payload' => $payload,
        ], $sid);

        if ($enqueueOnError) {
            $this->queueBroker->enqueue($payload, $msg, $sid);
        }
        return false;
    }
}
