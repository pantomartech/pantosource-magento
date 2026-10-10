<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Panto\Source\Api\ConfigInterface;

class Config implements ConfigInterface
{
    public const DEFAULT_INGESTION_BASE_URL = 'https://events.pantosource.com';

    public const LEGACY_STAGING_INGESTION_HOST = 'events.staging.pantosource.com';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getIngestionBaseUrl(?int $storeId = null): string
    {
        $configured = (string) $this->scopeConfig->getValue(
            self::XML_PATH_BASE_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return self::resolveIngestionBaseUrl($configured);
    }

    /**
     * Empty values and the retired staging host become the production ingestion URL.
     * Any other saved URL is kept so a custom endpoint still wins.
     */
    public static function resolveIngestionBaseUrl(string $configured): string
    {
        $trimmed = rtrim(trim($configured), '/');
        if ($trimmed === '' || self::isLegacyStagingIngestionUrl($trimmed)) {
            return self::DEFAULT_INGESTION_BASE_URL;
        }

        return $trimmed;
    }

    public static function isLegacyStagingIngestionUrl(string $url): bool
    {
        $candidate = trim($url);
        if ($candidate === '') {
            return false;
        }
        if (!preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://' . $candidate;
        }
        $host = parse_url($candidate, PHP_URL_HOST);

        return is_string($host) && strcasecmp($host, self::LEGACY_STAGING_INGESTION_HOST) === 0;
    }

    public function getApiKey(?int $storeId = null): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($value === '') {
            return '';
        }

        return (string) $this->encryptor->decrypt($value);
    }

    public function isFrontendTrackerEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FRONTEND_TRACKER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAttributionEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ATTRIBUTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isHashedPiiEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HASHED_PII,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDebugEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getConsentMode(?int $storeId = null): string
    {
        $m = (string) $this->scopeConfig->getValue(
            self::XML_PATH_CONSENT_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $m !== '' ? $m : 'none';
    }

    public function hasTrackingConsent(?int $storeId = null): bool
    {
        $mode = $this->getConsentMode($storeId);
        if ($mode === 'none') {
            return true;
        }
        if ($mode === 'basic' || $mode === 'strict') {
            return isset($_COOKIE['pantosource_consent']) && $_COOKIE['pantosource_consent'] === 'granted';
        }
        return false;
    }

    public function getEventsEndpointUrl(?int $storeId = null): string
    {
        $base = $this->getIngestionBaseUrl($storeId);
        return $base === '' ? '' : $base . '/events';
    }

    public function getAttributionLookbackDays(?int $storeId = null): int
    {
        $v = (int) $this->scopeConfig->getValue(
            self::XML_PATH_ATTRIBUTION_LOOKBACK_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $v > 0 ? $v : AttributionMetaBuilder::ATTRIBUTION_LOOKBACK_DAYS;
    }

    public function isSpaPageviewsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SPA_PAGEVIEWS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getPortalBaseUrl(?int $storeId = null): string
    {
        $v = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PORTAL_BASE_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return rtrim($v, '/');
    }
}
