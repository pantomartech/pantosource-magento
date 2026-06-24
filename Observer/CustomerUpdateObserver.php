<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Customer\Model\Customer;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class CustomerUpdateObserver implements ObserverInterface
{
    private const DEDUPE_TTL = 60;

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly CacheInterface $cache
    ) {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();
        if (!$customer instanceof Customer || !$customer->getId()) {
            return;
        }
        $customerId = (int) $customer->getId();
        $email = (string) $customer->getEmail();
        $hash = md5($email . (string) $customer->getFirstname() . (string) $customer->getLastname());
        $cacheKey = 'pantosource_cust_upd_' . $customerId . '_' . $hash;
        if ($this->cache->load($cacheKey)) {
            return;
        }
        $this->cache->save('1', $cacheKey, [], self::DEDUPE_TTL);

        $data = [
            'customer_id' => $customerId,
            'email' => $email,
            'context' => StorefrontContext::fromRequest($this->request, 'account', 'Account'),
        ];
        $this->dispatcher->dispatch('magento_customer_updated', $data, 'identify');
    }
}
