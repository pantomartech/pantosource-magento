<?php
declare(strict_types=1);

namespace Panto\Source\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Model\IngestionClient;
use Panto\Source\Model\QueueBroker;

class ProcessQueue
{
    public function __construct(
        private readonly QueueBroker $queueBroker,
        private readonly IngestionClient $ingestionClient,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(): void
    {
        $rows = $this->queueBroker->fetchDue(50);
        $defaultStoreId = (int) $this->storeManager->getDefaultStoreView()->getId();
        foreach ($rows as $row) {
            $entityId = (int) $row['entity_id'];
            $publicId = (string) $row['public_id'];
            $attempts = (int) $row['attempts'];
            $payload = json_decode((string) $row['payload'], true);
            if (!is_array($payload)) {
                $this->queueBroker->deleteByEntityId($entityId);
                continue;
            }
            $storeId = isset($row['store_id']) ? (int) $row['store_id'] : 0;
            if ($storeId <= 0) {
                $storeId = $defaultStoreId;
            }
            if (!$this->config->isEnabled($storeId)) {
                $this->queueBroker->deleteByEntityId($entityId);
                continue;
            }
            [$ok, , $msg] = $this->ingestionClient->send($payload, true, $storeId);
            if ($ok) {
                $this->queueBroker->clearLease($entityId);
                $this->queueBroker->deleteByEntityId($entityId);
                continue;
            }
            if ($attempts + 1 >= QueueBroker::MAX_ATTEMPTS) {
                $this->queueBroker->moveToDeadLetter($publicId, $row);
            } else {
                $this->queueBroker->bumpFailure($entityId, $attempts, $msg);
            }
            $this->queueBroker->clearLease($entityId);
        }
    }
}
