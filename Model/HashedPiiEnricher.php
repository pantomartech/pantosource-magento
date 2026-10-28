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
        $shipping = $order->getShippingAddress();
        $pick = static function (?object $primary, ?object $fallback, string $method): string {
            $value = '';
            if ($primary && method_exists($primary, $method)) {
                $value = trim((string) $primary->$method());
            }
            if ($value === '' && $fallback && method_exists($fallback, $method)) {
                $value = trim((string) $fallback->$method());
            }
            return $value;
        };
        $state = $pick($billing, $shipping, 'getRegionCode');
        if ($state === '') {
            $state = $pick($billing, $shipping, 'getRegion');
        }

        $data['hashed_user'] = HashedPiiHasher::hashFields([
            'email' => $email,
            'phone' => $pick($billing, $shipping, 'getTelephone'),
            'first_name' => $pick($billing, $shipping, 'getFirstname'),
            'last_name' => $pick($billing, $shipping, 'getLastname'),
            'city' => $pick($billing, $shipping, 'getCity'),
            'state' => $state,
            'zip' => $pick($billing, $shipping, 'getPostcode'),
            'country' => $pick($billing, $shipping, 'getCountryId'),
        ], array_values(array_filter([
            $pick($shipping, null, 'getCountryId'),
            $pick($billing, null, 'getCountryId'),
        ])));

        return $data;
    }
}
