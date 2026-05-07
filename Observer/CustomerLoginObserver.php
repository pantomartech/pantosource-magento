<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\CustomerProfileBuilder;
use Panto\Source\Model\EventDispatcher;

class CustomerLoginObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly CustomerFactory $customerFactory,
        private readonly CustomerProfileBuilder $customerProfileBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var CustomerInterface|null $customer */
        $customer = $observer->getEvent()->getCustomer();
        if (!$customer || !$customer->getId()) {
            return;
        }
        $data = [
            'customer_id' => (int) $customer->getId(),
            'email' => (string) $customer->getEmail(),
            'login_nonce' => bin2hex(random_bytes(16)),
            'context' => StorefrontContext::fromRequest($this->request, 'account', 'Login'),
        ];
        $model = $this->customerFactory->create()->load((int) $customer->getId());
        $profile = $this->customerProfileBuilder->profileFromCustomerModel($model);
        if ($profile !== []) {
            $data['customer_profile'] = $profile;
        }
        $this->dispatcher->dispatch('magento_customer_logged_in', $data);
    }
}
