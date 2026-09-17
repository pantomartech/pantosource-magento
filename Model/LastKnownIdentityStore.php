<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Helper\ExternalId;

/**
 * Persist login identity on Magento customer session so later logged-in
 * storefront events can fill missing user.email / user.profile fill-only.
 * Guest last-known is not applied; checkout/purchase events still attach quote/order PII.
 * Not checkout session (cleared after success) and not cookies.
 */
class LastKnownIdentityStore
{
    public const SESSION_KEY = 'pantosource_last_known_identity';

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function applyToData(array $data, int $storeId): array
    {
        if (!$this->config->hasTrackingConsent($storeId)) {
            return $data;
        }
        if (!$this->isLoggedIn()) {
            return $data;
        }
        $snapshot = $this->readSnapshot();
        if ($snapshot === []) {
            return $data;
        }
        if ($this->emptyString($data['email'] ?? null) && $this->emptyString($data['billing_email'] ?? null)
            && !$this->emptyString($snapshot['email'] ?? null)) {
            $data['email'] = (string) $snapshot['email'];
        }
        if (empty($data['customer_id']) && !$this->emptyString($snapshot['external_id'] ?? null)) {
            $data['customer_id'] = (int) $snapshot['external_id'];
        }
        if (empty($data['customer_profile']) && !empty($snapshot['profile']) && is_array($snapshot['profile'])) {
            $data['customer_profile'] = $snapshot['profile'];
        } elseif (!empty($data['customer_profile']) && is_array($data['customer_profile'])
            && !empty($snapshot['profile']) && is_array($snapshot['profile'])) {
            $data['customer_profile'] = $this->fillAssoc($data['customer_profile'], $snapshot['profile']);
        }
        if (empty($data['hashed_user']) && !empty($snapshot['hashed_user']) && is_array($snapshot['hashed_user'])) {
            $data['hashed_user'] = $snapshot['hashed_user'];
        } elseif (!empty($data['hashed_user']) && is_array($data['hashed_user'])
            && !empty($snapshot['hashed_user']) && is_array($snapshot['hashed_user'])) {
            $data['hashed_user'] = $this->fillAssoc($data['hashed_user'], $snapshot['hashed_user']);
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function persistFromData(array $data, int $storeId): void
    {
        if (!$this->config->hasTrackingConsent($storeId)) {
            return;
        }
        if (!$this->isLoggedIn()) {
            return;
        }
        $incoming = $this->snapshotFromData($data);
        if ($incoming === []) {
            return;
        }
        $merged = $this->unionAssoc($this->readSnapshot(), $incoming);
        $json = json_encode($merged);
        if ($json === false) {
            return;
        }
        $this->customerSession->setData(self::SESSION_KEY, $json);
    }

    private function isLoggedIn(): bool
    {
        try {
            return (bool) $this->customerSession->isLoggedIn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readSnapshot(): array
    {
        $raw = $this->customerSession->getData(self::SESSION_KEY);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function snapshotFromData(array $data): array
    {
        $email = '';
        if (!$this->emptyString($data['billing_email'] ?? null)) {
            $email = (string) $data['billing_email'];
        } elseif (!$this->emptyString($data['email'] ?? null)) {
            $email = (string) $data['email'];
        }
        $externalId = ExternalId::fromCustomerId($data['customer_id'] ?? null);
        $profile = [];
        if (!empty($data['customer_profile']) && is_array($data['customer_profile'])) {
            $profile = $data['customer_profile'];
        }
        $hashed = [];
        if (!empty($data['hashed_user']) && is_array($data['hashed_user'])) {
            $hashed = $data['hashed_user'];
        }
        $out = [];
        if ($email !== '') {
            $out['email'] = $email;
        }
        if ($externalId !== '') {
            $out['external_id'] = $externalId;
        }
        if ($profile !== []) {
            $out['profile'] = $profile;
        }
        if ($hashed !== []) {
            $out['hashed_user'] = $hashed;
        }
        return $out;
    }

    /**
     * Incoming non-empty keys win (purchase/login upgrades guest snapshot).
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private function unionAssoc(array $base, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (is_array($value)) {
                $prior = isset($base[$key]) && is_array($base[$key]) ? $base[$key] : [];
                $base[$key] = $this->unionAssoc($prior, $value);
                continue;
            }
            if (is_string($value) && $value !== '') {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    /**
     * Fill-only: keep present $base keys; copy missing from $fill.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $fill
     * @return array<string, mixed>
     */
    private function fillAssoc(array $base, array $fill): array
    {
        foreach ($fill as $key => $value) {
            if (is_array($value)) {
                $prior = isset($base[$key]) && is_array($base[$key]) ? $base[$key] : [];
                $base[$key] = $this->fillAssoc($prior, $value);
                continue;
            }
            if (is_string($value) && $value !== '' && $this->emptyString($base[$key] ?? null)) {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    private function emptyString(mixed $value): bool
    {
        return !is_string($value) || trim($value) === '';
    }
}
