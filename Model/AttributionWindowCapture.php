<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Helper\MatchingSignalFields;

/**
 * Persistent 30-day attribution window in cookie (Woo pantosource_capture_attribution_window parity).
 */
class AttributionWindowCapture
{
    public const COOKIE_NAME = 'pantosource_attribution';

    /** @var list<string> */
    public const ATTRIBUTION_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'ttclid',
        'msclkid',
        'gbraid',
        'wbraid',
        'fbp',
        'fbc',
        'ttp',
    ];

    public function __construct(
        private readonly HttpRequest $httpRequest,
        private readonly ConfigInterface $config,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory
    ) {
    }

    /**
     * @param array<string, mixed> $incoming Flat attribution params from request/body
     * @return array{first_touch: array<string, mixed>, last_touch: array<string, mixed>, meta: array<string, mixed>}
     */
    public function capture(int $storeId, array $incoming, string $shopHost, string $landingUrl = '', string $referrer = ''): array
    {
        if (!$this->config->isAttributionEnabled($storeId) || !$this->config->hasTrackingConsent($storeId)) {
            return ['first_touch' => [], 'last_touch' => [], 'meta' => []];
        }

        $existing = $this->readCookie();
        $first = isset($existing['first_touch']) && is_array($existing['first_touch']) ? $existing['first_touch'] : [];
        $last = isset($existing['last_touch']) && is_array($existing['last_touch']) ? $existing['last_touch'] : [];
        $capturedAt = isset($existing['captured_at']) && is_string($existing['captured_at'])
            ? $existing['captured_at']
            : '';

        if ($this->isExpired($capturedAt, $storeId)) {
            $first = [];
            $last = [];
            $capturedAt = '';
        }

        $touchParams = $incoming;
        if ($referrer !== '') {
            $touchParams['referrer'] = $referrer;
        }

        $shouldUpdate = AttributionGuardrails::shouldUpdateLastTouch($touchParams, $shopHost);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
        $hasIncoming = false;

        if ($shouldUpdate) {
            foreach (self::ATTRIBUTION_KEYS as $key) {
                if (!isset($incoming[$key]) || !is_string($incoming[$key]) || trim($incoming[$key]) === '') {
                    continue;
                }
                $val = trim($incoming[$key]);
                $hasIncoming = true;
                if (empty($first[$key])) {
                    $first[$key] = $val;
                }
                $last[$key] = $val;
            }

            if ($landingUrl !== '') {
                if (empty($first['landing_url'])) {
                    $first['landing_url'] = $landingUrl;
                }
                $last['landing_url'] = $landingUrl;
            }
            if ($referrer !== '') {
                if (empty($first['referrer'])) {
                    $first['referrer'] = $referrer;
                }
                $last['referrer'] = $referrer;
            }
        }

        $first = MatchingSignalFields::union($first, $incoming);
        $last = MatchingSignalFields::union($last, $incoming);

        if ($hasIncoming || $landingUrl !== '' || $referrer !== '' || MatchingSignalFields::hasAnyMatching($incoming)) {
            $first['captured_at'] = $first['captured_at'] ?? $now;
            $last['captured_at'] = $now;
            $capturedAt = $now;
            $this->writeCookie($first, $last, $capturedAt, $storeId);
        }

        return [
            'first_touch' => $first,
            'last_touch' => $last,
            'meta' => $this->buildMetaContext($first, $last, $capturedAt, $storeId),
        ];
    }

    /**
     * @return array{first_touch: array<string, mixed>, last_touch: array<string, mixed>, meta: array<string, mixed>}
     */
    public function readCurrent(int $storeId): array
    {
        if (!$this->config->isAttributionEnabled($storeId)) {
            return ['first_touch' => [], 'last_touch' => [], 'meta' => []];
        }
        $existing = $this->readCookie();
        $first = isset($existing['first_touch']) && is_array($existing['first_touch']) ? $existing['first_touch'] : [];
        $last = isset($existing['last_touch']) && is_array($existing['last_touch']) ? $existing['last_touch'] : [];
        $capturedAt = isset($existing['captured_at']) && is_string($existing['captured_at'])
            ? $existing['captured_at']
            : '';
        if ($this->isExpired($capturedAt, $storeId)) {
            return ['first_touch' => [], 'last_touch' => [], 'meta' => []];
        }
        return [
            'first_touch' => $first,
            'last_touch' => $last,
            'meta' => $this->buildMetaContext($first, $last, $capturedAt, $storeId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readCookie(): array
    {
        try {
            $raw = (string) $this->cookieManager->getCookie(self::COOKIE_NAME);
        } catch (\Throwable) {
            $raw = (string) $this->httpRequest->getCookie(self::COOKIE_NAME, '');
        }
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $first
     * @param array<string, mixed> $last
     */
    private function writeCookie(array $first, array $last, string $capturedAt, int $storeId): void
    {
        $payload = json_encode([
            'first_touch' => $first,
            'last_touch' => $last,
            'captured_at' => $capturedAt,
        ]);
        if ($payload === false) {
            return;
        }
        $days = $this->config->getAttributionLookbackDays($storeId);
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDuration($days * 86400)
            ->setPath('/')
            ->setHttpOnly(false)
            ->setSameSite('Lax');
        try {
            $this->cookieManager->setPublicCookie(self::COOKIE_NAME, $payload, $metadata);
        } catch (\Throwable) {
            // Cookie may fail in some contexts; read path still works on next request if set client-side.
        }
    }

    private function isExpired(string $capturedAt, int $storeId): bool
    {
        if ($capturedAt === '') {
            return false;
        }
        try {
            $ts = (new \DateTimeImmutable($capturedAt))->getTimestamp();
        } catch (\Throwable) {
            return true;
        }
        $ttl = $this->config->getAttributionLookbackDays($storeId) * 86400;
        return (time() - $ts) > $ttl;
    }

    /**
     * @param array<string, mixed> $first
     * @param array<string, mixed> $last
     * @return array<string, mixed>
     */
    private function buildMetaContext(array $first, array $last, string $capturedAt, int $storeId): array
    {
        $ctx = [];
        if ($capturedAt !== '') {
            $ctx['captured_at'] = $capturedAt;
        }
        $ctx['lookback_days'] = $this->config->getAttributionLookbackDays($storeId);
        $meta = AttributionMetaBuilder::buildAttributionMeta($first, $last, $ctx, $ctx['lookback_days']);
        return $meta ?? [];
    }
}
