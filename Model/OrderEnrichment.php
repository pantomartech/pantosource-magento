<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class OrderEnrichment
{
    public function __construct(
        private readonly OrderCollectionFactory $orderCollectionFactory
    ) {
    }

    public function isNewCustomer(OrderInterface $order): bool
    {
        $customerId = (int) $order->getCustomerId();
        if ($customerId > 0) {
            $collection = $this->orderCollectionFactory->create();
            $collection->addFieldToFilter('customer_id', $customerId);
            $collection->setPageSize(2);
            return NewCustomerDecision::isNew($customerId, (int) $collection->getSize(), 0);
        }

        $email = strtolower(trim((string) $order->getCustomerEmail()));
        if ($email === '') {
            return true;
        }

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_email', $email);
        $collection->addFieldToFilter('store_id', (int) $order->getStoreId());
        $collection->addFieldToFilter('state', ['nin' => ['canceled']]);
        $collection->setPageSize(2);
        return NewCustomerDecision::isNew(0, 0, (int) $collection->getSize());
    }

    /**
     * @return list<string>
     */
    public function getCouponCodes(OrderInterface $order): array
    {
        $codes = [];
        if (method_exists($order, 'getCouponCode')) {
            $code = (string) $order->getCouponCode();
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        if (method_exists($order, 'getCouponCodes')) {
            $raw = $order->getCouponCodes();
            if (is_array($raw)) {
                foreach ($raw as $c) {
                    $c = trim((string) $c);
                    if ($c !== '' && !in_array($c, $codes, true)) {
                        $codes[] = $c;
                    }
                }
            }
        }
        return $codes;
    }
}
