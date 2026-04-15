<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CustomerLogoutObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request
    ) {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();
        $customerId = $customer && $customer->getId() ? (int) $customer->getId() : 0;
        if ($customerId <= 0) {
            return;
        }
        $data = [
            'customer_id' => $customerId,
            'logout_nonce' => bin2hex(random_bytes(16)),
            'context' => StorefrontContext::fromRequest($this->request, 'account', 'Logout'),
        ];
        $this->dispatcher->dispatch('magento_customer_logged_out', $data);
    }
}
