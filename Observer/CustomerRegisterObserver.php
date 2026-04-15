<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CustomerRegisterObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request
    ) {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();
        if (!$customer || !$customer->getId()) {
            return;
        }
        $data = [
            'customer_id' => (int) $customer->getId(),
            'email' => (string) $customer->getEmail(),
            'context' => StorefrontContext::fromRequest($this->request, 'account', 'Register'),
        ];
        $this->dispatcher->dispatch('magento_customer_signed_up', $data);
    }
}
