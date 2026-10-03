<?php
declare(strict_types=1);

namespace Panto\Source\Api;

interface ConfigInterface
{
    public const XML_PATH_ENABLED = 'pantosource/general/enabled';
    public const XML_PATH_BASE_URL = 'pantosource/ingestion/base_url';
    public const XML_PATH_API_KEY = 'pantosource/ingestion/api_key';
    public const XML_PATH_FRONTEND_TRACKER = 'pantosource/tracking/frontend_tracker';
    public const XML_PATH_ATTRIBUTION = 'pantosource/tracking/attribution';
    public const XML_PATH_HASHED_PII = 'pantosource/tracking/hashed_pii';
    public const XML_PATH_DEBUG = 'pantosource/tracking/debug_mode';
    public const XML_PATH_CONSENT_MODE = 'pantosource/tracking/consent_mode';
    public const XML_PATH_ATTRIBUTION_LOOKBACK_DAYS = 'pantosource/tracking/attribution_lookback_days';
    public const XML_PATH_SPA_PAGEVIEWS = 'pantosource/tracking/spa_pageviews';
    public const XML_PATH_PORTAL_BASE_URL = 'pantosource/portal/base_url';

    public function isEnabled(?int $storeId = null): bool;

    public function getIngestionBaseUrl(?int $storeId = null): string;

    public function getApiKey(?int $storeId = null): string;

    public function isFrontendTrackerEnabled(?int $storeId = null): bool;

    public function isAttributionEnabled(?int $storeId = null): bool;

    public function isHashedPiiEnabled(?int $storeId = null): bool;

    public function isDebugEnabled(?int $storeId = null): bool;

    /** @return string none|basic|strict */
    public function getConsentMode(?int $storeId = null): string;

    public function hasTrackingConsent(?int $storeId = null): bool;

    public function getEventsEndpointUrl(?int $storeId = null): string;

    public function getAttributionLookbackDays(?int $storeId = null): int;

    public function isSpaPageviewsEnabled(?int $storeId = null): bool;

    public function getPortalBaseUrl(?int $storeId = null): string;
}
