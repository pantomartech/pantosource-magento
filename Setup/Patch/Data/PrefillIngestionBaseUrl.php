<?php
declare(strict_types=1);

namespace Panto\Source\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Model\Config;

/**
 * Rewrites saved ingestion base URLs that are blank or still point at the retired staging host.
 */
class PrefillIngestionBaseUrl implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();
        $table = $this->moduleDataSetup->getTable('core_config_data');
        $select = $connection->select()
            ->from($table, ['config_id', 'value'])
            ->where('path = ?', ConfigInterface::XML_PATH_BASE_URL);

        foreach ($connection->fetchAll($select) as $row) {
            $current = (string) ($row['value'] ?? '');
            $resolved = Config::resolveIngestionBaseUrl($current);
            if ($resolved === rtrim(trim($current), '/')) {
                continue;
            }
            $connection->update(
                $table,
                ['value' => Config::DEFAULT_INGESTION_BASE_URL],
                ['config_id = ?' => $row['config_id']]
            );
        }

        $connection->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
