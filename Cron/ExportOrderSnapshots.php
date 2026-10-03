<?php
declare(strict_types=1);

namespace Panto\Source\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Model\OrderSnapshotExporter;

class ExportOrderSnapshots
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ConfigInterface $config,
        private readonly OrderSnapshotExporter $exporter
    ) {
    }

    public function execute(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            if (!$this->config->isEnabled($storeId) || $this->config->getApiKey($storeId) === '') {
                continue;
            }
            $this->exporter->exportForStore($storeId);
        }
    }
}
