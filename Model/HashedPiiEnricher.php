<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Panto\Source\Api\ConfigInterface;

/**
 * Adds hashed_user to event data when hashed PII is enabled and consent allows.
 */
class HashedPiiEnricher
{
    public function __construct(
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function enrich(OrderInterface $order, array $data, ?int $storeId = null): array
    {
        $sid = $storeId ?? (int) $order->getStoreId();
        if (!$this->config->isHashedPiiEnabled($sid) || !$this->config->hasTrackingConsent($sid)) {
            return $data;
        }

        $email = (string) $order->getCustomerEmail();
        $billing = $order->getBillingAddress();
        $phone = $billing ? (string) $billing->getTelephone() : '';
        $first = $billing ? (string) $billing->getFirstname() : '';
        $last = $billing ? (string) $billing->getLastname() : '';
        $city = $billing ? (string) $billing->getCity() : '';
        $state = '';
        $zip = $billing ? (string) $billing->getPostcode() : '';
        $country = $billing ? (string) $billing->getCountryId() : '';
        if ($billing) {
            $state = (string) $billing->getRegion();
            if ($state === '' && method_exists($billing, 'getRegionCode')) {
                $state = (string) $billing->getRegionCode();
            }
        }

        $data['hashed_user'] = HashedPiiHasher::hashFields([
            'email' => $email,
            'phone' => $phone,
            'first_name' => $first,
            'last_name' => $last,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'country' => $country,
        ]);

        return $data;
    }
}
