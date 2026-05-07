<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;

class PayloadBuilder
{
    private const SOURCE = 'pantosource_magento';

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly SemanticKeyBuilder $semanticKeyBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpRequest $httpRequest,
        private readonly CustomerSession $customerSession,
        private readonly CustomerProfileBuilder $customerProfileBuilder
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function build(string $eventName, array $data, string $eventTypeOverride = '', ?int $storeId = null): array
    {
        $schema = EventRegistry::getSchema($eventName);
        $type = $eventTypeOverride !== '' ? $eventTypeOverride : ($schema['type'] ?? 'track');

        if (!isset($data['schema_version'])) {
            $data['schema_version'] = '1.0';
        }
        if (!isset($data['semantic_key'])) {
            $data['semantic_key'] = $this->semanticKeyBuilder->build($eventName, $data);
        }

        $store = $storeId !== null
            ? $this->storeManager->getStore($storeId)
            : $this->storeManager->getStore();
        $baseUrl = rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_WEB), '/');
        try {
            $host = parse_url($baseUrl, PHP_URL_HOST) ?: $store->getCode();
        } catch (\Throwable) {
            $host = $store->getCode();
        }

        $data['shop'] = $host;
        if (!isset($data['anonymous_id'])) {
            $data['anonymous_id'] = $this->httpRequest->getCookie('panto_anonymous_id', null)
                ?? null;
        }
        if (!isset($data['session_id'])) {
            $data['session_id'] = $this->httpRequest->getCookie('panto_session_id', null)
                ?? null;
        }

        $sid = (int) $store->getId();
        $data = $this->attachMetaBrowserIdsFromCookies($data, $sid);

        $url = $this->resolveUrl($baseUrl);
        $timestamp = $this->nowIsoUtc();

        $payload = [
            'event' => [
                'id' => $this->uuidV4(),
                'name' => $eventName,
                'type' => $type,
                'timestamp' => $timestamp,
                'source' => self::SOURCE,
                'channel' => 'web',
            ],
            'timestamp' => $timestamp,
            'url' => $url,
            'data' => $data,
        ];

        $payload['user'] = $this->buildUser($data, $sid);
        $payload['context'] = $this->buildContext($data, $url, $sid);
        $payload['commerce'] = $this->buildCommerce($eventName, $data);
        $payload['marketing'] = $this->buildMarketing($data, $sid);
        $payload['privacy'] = $this->buildPrivacy($sid);
        $payload['extensions'] = $this->buildExtensions($data);

        if ($this->config->hasTrackingConsent($sid) && $this->config->isEnabled($sid)) {
            $ip = $this->getClientIp();
            if ($ip !== '') {
                $payload['network'] = ['client_ip' => $ip];
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildUser(array $data, int $storeId): array
    {
        $consent = $this->config->hasTrackingConsent($storeId);
        $externalId = isset($data['customer_id']) ? (string) $data['customer_id'] : '';
        if ($externalId === '' && $this->customerSession->isLoggedIn()) {
            $externalId = (string) $this->customerSession->getCustomerId();
        }
        $email = '';
        if (isset($data['billing_email'])) {
            $email = (string) $data['billing_email'];
        } elseif (isset($data['email'])) {
            $email = (string) $data['email'];
        } elseif ($this->customerSession->isLoggedIn()) {
            $email = (string) $this->customerSession->getCustomer()->getEmail();
        }

        $ua = '';
        if (!empty($data['context']['user_agent'])) {
            $ua = (string) $data['context']['user_agent'];
        } elseif (!empty($data['context']['device']['user_agent'])) {
            $ua = (string) $data['context']['device']['user_agent'];
        } elseif ($consent) {
            $ua = (string) $this->httpRequest->getServer('HTTP_USER_AGENT', '');
        }

        $identifiers = [];
        if (!empty($data['hashed_user']) && is_array($data['hashed_user'])) {
            $identifiers = array_filter(
                array_map('strval', array_intersect_key($data['hashed_user'], array_flip(
                    ['email_sha256', 'phone_sha256', 'first_name_sha256', 'last_name_sha256']
                )))
            );
        }

        /** @var array<string, mixed> $profile */
        $profile = [];
        if (!empty($data['customer_profile']) && is_array($data['customer_profile'])) {
            $profile = $data['customer_profile'];
        } elseif ($consent && $this->customerSession->isLoggedIn()) {
            $profile = $this->customerProfileBuilder->profileFromCustomerModel($this->customerSession->getCustomer());
        }

        $out = [
            'external_id' => $externalId,
            'email' => $email,
            'user_agent' => $ua,
            'identifiers' => empty($identifiers) ? new \stdClass() : $identifiers,
        ];
        if ($profile !== []) {
            $out['profile'] = $profile;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildContext(array $data, string $url, int $storeId): array
    {
        $consent = $this->config->hasTrackingConsent($storeId);
        $ctx = isset($data['context']) && is_array($data['context']) ? $data['context'] : [];
        $tz = (string) ($ctx['timezone'] ?? '');
        $path = (string) ($ctx['path'] ?? '');
        if ($path === '' && $url !== '') {
            $p = parse_url($url, PHP_URL_PATH);
            $q = parse_url($url, PHP_URL_QUERY);
            $path = ($p ?: '/') . ($q ? '?' . $q : '');
        }
        $pageUrl = (string) ($ctx['page']['url'] ?? $url);
        $title = (string) ($ctx['page']['title'] ?? '');
        $referrer = (string) ($ctx['page']['referrer'] ?? $ctx['referrer'] ?? '');
        $pageType = (string) ($ctx['page']['type'] ?? $ctx['page_type'] ?? '');
        $platform = (string) ($ctx['platform'] ?? '');
        if ($platform === '' && $consent) {
            $platform = (string) $this->httpRequest->getServer('HTTP_SEC_CH_UA_PLATFORM', '');
        }
        $ua = '';
        if (!empty($ctx['user_agent'])) {
            $ua = (string) $ctx['user_agent'];
        } elseif (!empty($ctx['device']['user_agent'])) {
            $ua = (string) $ctx['device']['user_agent'];
        } elseif ($consent) {
            $ua = (string) $this->httpRequest->getServer('HTTP_USER_AGENT', '');
        }

        return [
            'timezone' => $tz,
            'page' => [
                'url' => $pageUrl,
                'path' => $path,
                'title' => $title,
                'referrer' => $referrer,
                'type' => $pageType,
            ],
            'device' => [
                'platform' => $platform,
                'user_agent' => $ua,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|object
     */
    private function buildCommerce(string $eventName, array $data): array|object
    {
        $commerce = [];
        if (isset($data['order_id'])) {
            $commerce['order_id'] = (string) $data['order_id'];
        }
        if (isset($data['currency'])) {
            $commerce['currency'] = (string) $data['currency'];
        }
        if (isset($data['total']) && is_numeric($data['total'])) {
            $commerce['value'] = (float) $data['total'];
        } elseif (isset($data['cart_total']) && is_numeric($data['cart_total'])) {
            $commerce['value'] = (float) $data['cart_total'];
        }
        if (!empty($data['order_totals']) && is_array($data['order_totals'])) {
            $ot = $data['order_totals'];
            foreach (['tax_total' => 'tax', 'shipping_total' => 'shipping', 'discount_total' => 'discount'] as $from => $to) {
                if (isset($ot[$from]) && is_numeric($ot[$from])) {
                    $commerce[$to] = (float) $ot[$from];
                }
            }
        }
        if (!empty($data['items']) && is_array($data['items'])) {
            $items = [];
            foreach ($data['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $items[] = array_filter([
                    'id' => isset($item['product_id']) ? (string) $item['product_id'] : '',
                    'parent_id' => isset($item['parent_id']) ? (string) $item['parent_id'] : '',
                    'variant_id' => isset($item['variation_id']) ? (string) $item['variation_id'] : '',
                    'name' => isset($item['title']) ? (string) $item['title'] : (string) ($item['name'] ?? ''),
                    'category' => (string) ($item['category'] ?? ''),
                    'quantity' => isset($item['quantity']) && is_numeric($item['quantity']) ? (float) $item['quantity'] : null,
                    'price' => isset($item['unit_price']) && is_numeric($item['unit_price'])
                        ? (float) $item['unit_price']
                        : (isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null),
                ], static fn ($v) => $v !== null && $v !== '');
            }
            if ($items !== []) {
                $commerce['items'] = $items;
            }
        }

        return $commerce === [] ? new \stdClass() : $commerce;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array<string, mixed>>
     */
    private function buildMarketing(array $data, int $storeId): array
    {
        $ctx = isset($data['context']) && is_array($data['context']) ? $data['context'] : [];
        $attrib = isset($ctx['attribution']) && is_array($ctx['attribution']) ? $ctx['attribution'] : [];
        $first = isset($attrib['first_touch']) && is_array($attrib['first_touch']) ? $attrib['first_touch'] : [];
        $last = isset($attrib['last_touch']) && is_array($attrib['last_touch']) ? $attrib['last_touch'] : [];

        if ($this->config->isAttributionEnabled($storeId)) {
            $attributionKeys = [
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
            ];
            foreach ($attributionKeys as $mk) {
                if (empty($data[$mk]) || !is_string($data[$mk])) {
                    continue;
                }
                $val = trim($data[$mk]);
                if ($val === '') {
                    continue;
                }
                if (empty($first[$mk])) {
                    $first[$mk] = $val;
                }
                $last[$mk] = $val;
            }
        }

        return [
            'attribution' => [
                'first_touch' => $first === [] ? new \stdClass() : $first,
                'last_touch' => $last === [] ? new \stdClass() : $last,
            ],
        ];
    }

    /**
     * Merge Meta browser cookies into payload data for canonical marketing mapping.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attachMetaBrowserIdsFromCookies(array $data, int $storeId): array
    {
        if (!$this->config->hasTrackingConsent($storeId) || !$this->config->isAttributionEnabled($storeId)) {
            return $data;
        }
        foreach (['fbp' => '_fbp', 'fbc' => '_fbc'] as $field => $cookieName) {
            if (!empty($data[$field])) {
                continue;
            }
            $raw = (string) $this->httpRequest->getCookie($cookieName, '');
            if ($raw !== '') {
                $data[$field] = $raw;
            }
        }

        return $data;
    }

    private function buildPrivacy(int $storeId): array
    {
        $granted = $this->config->hasTrackingConsent($storeId);
        $mode = $this->config->getConsentMode($storeId);
        return [
            'consent' => [
                'ad_storage' => $granted,
                'analytics_storage' => $granted,
            ],
            'regulation' => [
                'gdpr' => $mode !== 'none',
                'ccpa' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildExtensions(array $data): array
    {
        return [
            'pantosource' => array_filter([
                'schema_version' => isset($data['schema_version']) ? (string) $data['schema_version'] : '',
                'semantic_key' => isset($data['semantic_key']) ? (string) $data['semantic_key'] : '',
            ]),
        ];
    }

    private function resolveUrl(string $fallbackBase): string
    {
        $uri = (string) $this->httpRequest->getRequestUri();
        if ($uri !== '') {
            $scheme = $this->httpRequest->isSecure() ? 'https' : 'http';
            $host = $this->httpRequest->getHttpHost();
            if ($host !== '') {
                return $scheme . '://' . $host . $uri;
            }
        }
        return $fallbackBase . '/';
    }

    private function getClientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            $raw = (string) $this->httpRequest->getServer($h, '');
            if ($raw === '') {
                continue;
            }
            if ($h === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $raw);
                $raw = trim($parts[0]);
            }
            if (filter_var($raw, FILTER_VALIDATE_IP)) {
                return $raw;
            }
        }
        return '';
    }

    private function nowIsoUtc(): string
    {
        $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d\TH:i:s.v\Z');
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}
