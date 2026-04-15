<?php
declare(strict_types=1);

namespace Panto\Source\Plugin;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Panto\Source\Model\EventDispatcher;

class CustomerRepositoryDeletePlugin
{
    public function __construct(
        private readonly EventDispatcher $dispatcher
    ) {
    }

    public function afterDelete(
        CustomerRepositoryInterface $subject,
        $result,
        CustomerInterface $customer
    ) {
        unset($subject, $result);
        if ($customer->getId()) {
            $this->dispatcher->dispatch('magento_customer_deleted', ['customer_id' => (int) $customer->getId()]);
        }
        return $result;
    }
}
