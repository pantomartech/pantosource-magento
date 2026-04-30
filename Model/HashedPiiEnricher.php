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

        $hash = static function (string $value): string {
            $trimmed = strtolower(trim($value));
            return $trimmed === '' ? '' : hash('sha256', $trimmed);
        };
        $normalizePhone = static function (string $value): string {
            return preg_replace('/\D+/', '', $value) ?? '';
        };

        $email = (string) $order->getCustomerEmail();
        $billing = $order->getBillingAddress();
        $phone = $billing ? (string) $billing->getTelephone() : '';
        $first = $billing ? (string) $billing->getFirstname() : '';
        $last = $billing ? (string) $billing->getLastname() : '';

        $data['hashed_user'] = array_filter(
            [
                'email' => $hash($email),
                'phone' => $hash($normalizePhone($phone)),
                'first_name' => $hash($first),
                'last_name' => $hash($last),
            ],
            static fn (string $v): bool => $v !== ''
        );

        return $data;
    }
}
