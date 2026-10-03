<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Panto\Source\Helper\OrderSnapshotMapper;
use Psr\Log\LoggerInterface;

/**
 * Builds and uploads order snapshot batches for one store view (MG-2 / MG-6).
 */
class OrderSnapshotExporter
{
    private const PLATFORM = 'magento';
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly ConversionDedupeRepository $dedupeRepository,
        private readonly CheckoutIdentityRepository $checkoutIdentity,
        private readonly OrderSnapshotTombstoneRepository $tombstones,
        private readonly PortalStorefrontClient $portalClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function exportForStore(int $storeId): void
    {
        [$syncOk, $syncStatus, $syncState] = $this->portalClient->getSyncState($storeId);
        if (!$syncOk || !is_array($syncState)) {
            $this->logger->info('PantoSource snapshot export skipped: sync-state unavailable', [
                'store_id' => $storeId,
                'http_status' => $syncStatus,
            ]);
            return;
        }
        if (empty($syncState['enabled'])) {
            return;
        }

        $overlapMinutes = (int) ($syncState['overlap_minutes'] ?? 60);
        $fullWindowDays = (int) ($syncState['full_window_days'] ?? 30);
        $maxBatch = (int) ($syncState['max_batch_size'] ?? self::BATCH_SIZE);
        if ($maxBatch <= 0) {
            $maxBatch = self::BATCH_SIZE;
        }

        $mode = !empty($syncState['full_window_due']) ? 'full' : 'incremental';
        $windowEnd = gmdate('Y-m-d\TH:i:s\Z');
        if ($mode === 'full') {
            $windowStart = gmdate('Y-m-d\TH:i:s\Z', time() - ($fullWindowDays * 86400));
        } else {
            $since = isset($syncState['incremental_since']) && is_string($syncState['incremental_since'])
                ? strtotime($syncState['incremental_since'])
                : time() - ($overlapMinutes * 60);
            if ($since === false) {
                $since = time() - ($overlapMinutes * 60);
            }
            $windowStart = gmdate('Y-m-d\TH:i:s\Z', $since - ($overlapMinutes * 60));
        }

        $runId = $this->uuidV4();
        $page = 1;
        $isLast = true;
        $tombstoneRows = $this->tombstones->drainForStore($storeId, $maxBatch);
        $tombstonePayload = [];
        $tombstoneIds = [];
        foreach ($tombstoneRows as $row) {
            $tombstoneIds[] = (int) $row['order_id'];
            $tombstonePayload[] = OrderSnapshotMapper::tombstone(
                (int) $row['order_id'],
                (string) $row['tombstone_at']
            );
        }

        do {
            $criteria = $this->buildCriteria($storeId, $mode, $windowStart, $page, $maxBatch);
            $result = $this->orderRepository->getList($criteria);
            $items = $result->getItems();
            $orders = [];
            foreach ($items as $order) {
                if (!$order instanceof OrderInterface) {
                    continue;
                }
                $orderId = (int) $order->getEntityId();
                $orders[] = OrderSnapshotMapper::toSnapshot(
                    $this->orderToArray($order),
                    $this->checkoutIdentity->getSalesChannel($orderId),
                    $this->dedupeRepository->isPurchaseEmitted($orderId)
                );
            }

            if ($page === 1 && $tombstonePayload !== []) {
                $orders = array_merge($tombstonePayload, $orders);
            }

            if ($orders === []) {
                if ($page === 1 && $tombstonePayload === []) {
                    return;
                }
                if ($page > 1) {
                    break;
                }
            }

            $totalCount = (int) $result->getTotalCount();
            $isLast = ($page * $maxBatch) >= $totalCount;

            $body = [
                'schema_version' => 1,
                'platform' => self::PLATFORM,
                'run' => [
                    'run_id' => $runId,
                    'mode' => $mode,
                    'window_start' => $windowStart,
                    'window_end' => $windowEnd,
                    'page' => $page,
                    'is_last_page' => $isLast,
                ],
                'orders' => $orders,
            ];

            if (!$this->uploadWithRetry($storeId, $body)) {
                return;
            }

            if ($page === 1 && $tombstoneIds !== []) {
                $this->tombstones->deleteByOrderIds($tombstoneIds);
            }

            $page++;
        } while (!$isLast);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function uploadWithRetry(int $storeId, array $body): bool
    {
        $attempts = 0;
        while ($attempts < 3) {
            $attempts++;
            [$ok, $status, , ] = $this->portalClient->uploadOrderSnapshots($storeId, $body);
            if ($ok) {
                return true;
            }
            if ($status === 400 || $status === 401 || $status === 403) {
                $this->logger->warning('PantoSource snapshot upload rejected', [
                    'store_id' => $storeId,
                    'http_status' => $status,
                ]);
                return false;
            }
            if ($attempts < 3) {
                sleep(min(30, 5 * $attempts));
            }
        }

        return false;
    }

    private function buildCriteria(
        int $storeId,
        string $mode,
        string $windowStartIso,
        int $page,
        int $pageSize
    ): \Magento\Framework\Api\SearchCriteria {
        $builder = $this->searchCriteriaBuilderFactory->create();
        $builder->setCurrentPage($page);
        $builder->setPageSize($pageSize);
        $builder->addFilter('store_id', (string) $storeId, 'eq');

        $windowStartMysql = gmdate('Y-m-d H:i:s', strtotime($windowStartIso) ?: time());
        if ($mode === 'full') {
            $builder->addFilter('created_at', $windowStartMysql, 'gteq');
        } else {
            $builder->addFilter('updated_at', $windowStartMysql, 'gteq');
        }

        $builder->addSortOrder('entity_id', 'ASC');

        return $builder->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderToArray(OrderInterface $order): array
    {
        return [
            'entity_id' => (int) $order->getEntityId(),
            'increment_id' => (string) $order->getIncrementId(),
            'created_at' => (string) $order->getCreatedAt(),
            'updated_at' => (string) $order->getUpdatedAt(),
            'status' => (string) $order->getStatus(),
            'state' => (string) $order->getState(),
            'order_currency_code' => (string) $order->getOrderCurrencyCode(),
            'grand_total' => $order->getGrandTotal(),
            'subtotal' => $order->getSubtotal(),
            'tax_amount' => $order->getTaxAmount(),
            'shipping_amount' => $order->getShippingAmount(),
            'discount_amount' => $order->getDiscountAmount(),
            'total_refunded' => $order->getTotalRefunded(),
        ];
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
