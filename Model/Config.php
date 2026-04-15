<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panto\Source\Api\ConfigInterface;

class Config implements ConfigInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
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
        $v = (string) $this->scopeConfig->getValue(
            self::XML_PATH_BASE_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return rtrim($v, '/');
    }

    public function getApiKey(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
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
        if ($mode === 'basic') {
            // Placeholder: treat as granted unless extension sets cookie "denied"
            return true;
        }
        // strict: require explicit consent cookie (merchant can align with CMP)
        return isset($_COOKIE['pantosource_consent']) && $_COOKIE['pantosource_consent'] === 'granted';
    }

    public function getEventsEndpointUrl(?int $storeId = null): string
    {
        $base = $this->getIngestionBaseUrl($storeId);
        return $base === '' ? '' : $base . '/events';
    }
}
