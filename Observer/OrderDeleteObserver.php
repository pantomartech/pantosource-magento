<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Panto\Source\Model\OrderSnapshotTombstoneRepository;

/**
 * Queues order delete tombstones for the next snapshot export run (MG-4).
 */
class OrderDeleteObserver implements ObserverInterface
{
    public function __construct(
        private readonly OrderSnapshotTombstoneRepository $tombstones
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }
        $orderId = (int) $order->getEntityId();
        if ($orderId <= 0) {
            return;
        }
        $this->tombstones->queue($orderId, (int) $order->getStoreId());
    }
}
