<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;

use Panto\Source\Helper\BrowserContextBridge;
use Panto\Source\Helper\CanonicalContextPath;
use Panto\Source\Helper\ClientOccurredAt;
use Panto\Source\Helper\ExternalId;
use Panto\Source\Helper\MatchingSignalFields;
use Panto\Source\Helper\PlatformString;
use Panto\Source\Helper\SessionLandingPolicy;

class PayloadBuilder
{
    private const SOURCE = 'pantosource_magento';

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly SemanticKeyBuilder $semanticKeyBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpRequest $httpRequest,
        private readonly CustomerSession $customerSession,
        private readonly CustomerProfileBuilder $customerProfileBuilder,
        private readonly AttributionWindowCapture $attributionWindow,
        private readonly BrowserContextBridge $browserContextBridge,
        private readonly LastKnownIdentityStore $lastKnownIdentity
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
        $data = $this->lastKnownIdentity->applyToData($data, $sid);
        if (empty($data['customer_profile']) && $this->config->hasTrackingConsent($sid) && $this->customerSession->isLoggedIn()) {
            $data['customer_profile'] = $this->customerProfileBuilder->profileFromCustomerModel(
                $this->customerSession->getCustomer()
            );
        }
        if (
            empty($data['hashed_user'])
            && $this->config->isHashedPiiEnabled($sid)
            && $this->config->hasTrackingConsent($sid)
        ) {
            $profile = is_array($data['customer_profile'] ?? null) ? $data['customer_profile'] : [];
            $email = (string) ($data['billing_email'] ?? $data['email'] ?? '');
            $hashed = HashedPiiHasher::fromProfile($profile, $email);
            if ($hashed !== []) {
                $data['hashed_user'] = $hashed;
            }
        }
        $storedIp = MatchingSignalFields::nonempty($data['last_client_ip'] ?? '');
        $preferStoredIp = $storedIp !== ''
            && (bool) filter_var($storedIp, FILTER_VALIDATE_IP)
            && !MatchingSignalFields::hasLiveBrowserCookies($data);
        $data = $this->browserContextBridge->mergeIntoData($data);
        $data = $this->attachMetaBrowserIdsFromCookies($data, $sid);

        $url = $this->resolveUrl($baseUrl);
        $timestamp = $this->nowIsoUtc();
        $clientOccurred = '';
        if (isset($data['occurred_at'])) {
            $clientOccurred = ClientOccurredAt::normalize($data['occurred_at']);
            unset($data['occurred_at']);
        }
        if ($clientOccurred !== '') {
            $timestamp = $clientOccurred;
        }

        $payload = [
            'schema_version' => '2.0',
            'event' => [
                'id' => $this->uuidV4(),
                'name' => $eventName,
                'type' => $type,
                'occurred_at' => $timestamp,
                'source' => self::SOURCE,
                'channel' => 'web',
                'semantic_key' => (string) $data['semantic_key'],
            ],
            'identity' => [
                'shop' => $host,
                'anonymous_id' => $data['anonymous_id'] ?? null,
                'session_id' => $data['session_id'] ?? null,
            ],
            'user' => $this->buildUser($data, $sid),
            'context' => $this->buildContext($data, $url, $sid),
            'product' => $this->buildProduct($data),
            'cart' => $this->buildCart($data),
            'commerce' => $this->buildCommerce($eventName, $data),
            'marketing' => $this->buildMarketing($data, $sid),
            'privacy' => $this->buildPrivacy($sid),
            'extensions' => $this->buildExtensionsFromData($data),
            'data' => $this->slimEventData($data),
        ];

        if ($this->config->hasTrackingConsent($sid) && $this->config->isEnabled($sid)) {
            $ip = $preferStoredIp ? $storedIp : $this->getClientIp();
            if ($ip !== '') {
                $payload['network'] = ['client_ip' => $ip];
            }
        }

        $this->lastKnownIdentity->persistFromData($data, $sid);

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|object
     */
    private function buildProduct(array $data): array|object
    {
        $product = CommerceFields::hoistProduct($data);
        return $product === [] ? new \stdClass() : $product;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|object
     */
    private function buildCart(array $data): array|object
    {
        $cart = [];
        if (isset($data['cart_id'])) {
            $cart['cart_id'] = (string) $data['cart_id'];
        }
        return $cart === [] ? new \stdClass() : $cart;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|object
     */
    private function slimEventData(array $data): array|object
    {
        $strip = [
            'schema_version',
            'semantic_key',
            'shop',
            'anonymous_id',
            'session_id',
            'customer_profile',
            'context',
            'hashed_user',
            'attribution_snapshot',
            'browser_fingerprint',
            'last_client_ip',
            'last_user_agent',
            'last_cookies',
            'occurred_at',
        ];
        $out = $data;
        foreach ($strip as $key) {
            unset($out[$key]);
        }
        return $out === [] ? new \stdClass() : $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildUser(array $data, int $storeId): array
    {
        $consent = $this->config->hasTrackingConsent($storeId);
        $externalId = isset($data['customer_id'])
            ? ExternalId::fromCustomerId($data['customer_id'])
            : '';
        if ($externalId === '' && $this->customerSession->isLoggedIn()) {
            $externalId = ExternalId::fromCustomerId($this->customerSession->getCustomerId());
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
                    ['email_sha256', 'email_google_sha256', 'email_microsoft_sha256',
                     'phone_sha256', 'phone_e164_sha256', 'first_name_sha256', 'last_name_sha256',
                     'city_sha256', 'state_sha256', 'zip_sha256', 'country_sha256']
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
            'identifiers' => empty($identifiers) ? new \stdClass() : $identifiers,
        ];
        unset($ua);
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
        $path = CanonicalContextPath::resolve($ctx, $url);
        $pageUrl = (string) ($ctx['page']['url'] ?? '');
        if ($pageUrl === '') {
            $pageUrl = $url;
        }
        $title = (string) ($ctx['page']['title'] ?? '');
        $referrer = (string) ($ctx['page']['referrer'] ?? $ctx['referrer'] ?? '');
        $pageType = (string) ($ctx['page']['type'] ?? $ctx['page_type'] ?? '');
        $platform = PlatformString::normalize((string) ($ctx['platform'] ?? ''));
        if ($platform === '' && $consent) {
            $platform = PlatformString::normalize(
                (string) $this->httpRequest->getServer('HTTP_SEC_CH_UA_PLATFORM', '')
            );
        }
        $ua = '';
        if (!empty($ctx['user_agent'])) {
            $ua = (string) $ctx['user_agent'];
        } elseif (!empty($ctx['device']['user_agent'])) {
            $ua = (string) $ctx['device']['user_agent'];
        } elseif (!empty($data['last_user_agent'])) {
            $ua = (string) $data['last_user_agent'];
        } elseif ($consent) {
            $ua = (string) $this->httpRequest->getServer('HTTP_USER_AGENT', '');
        }

        $session = [];
        if (!empty($ctx['session']) && is_array($ctx['session'])) {
            $session = $ctx['session'];
        } elseif (!empty($ctx['landing_url']) && is_string($ctx['landing_url'])) {
            $session['landing_url'] = (string) $ctx['landing_url'];
        }

        $device = [
            'platform' => $platform,
            'user_agent' => $ua,
        ];
        if (!empty($ctx['device']) && is_array($ctx['device'])) {
            $device = array_merge($device, array_filter($ctx['device'], static fn ($v) => $v !== null && $v !== ''));
        }
        if (isset($device['platform'])) {
            $device['platform'] = PlatformString::normalize((string) $device['platform']);
        }

        return [
            'timezone' => $tz,
            'session' => $session === [] ? new \stdClass() : $session,
            'page' => [
                'url' => $pageUrl,
                'path' => $path,
                'title' => $title,
                'referrer' => $referrer,
                'type' => $pageType,
            ],
            'device' => $device,
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
        $paymentMethod = CommerceFields::resolvePaymentMethod($data);
        if ($paymentMethod !== '') {
            $commerce['payment_method'] = $paymentMethod;
        }
        if (isset($data['payment_status']) && is_string($data['payment_status']) && $data['payment_status'] !== '') {
            $commerce['payment_status'] = $data['payment_status'];
        }
        if (isset($data['sales_channel']) && is_string($data['sales_channel']) && $data['sales_channel'] !== '') {
            $commerce['sales_channel'] = $data['sales_channel'];
        }
        if (!empty($data['items']) && is_array($data['items'])) {
            $items = [];
            foreach ($data['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $variantId = CommerceFields::lineVariantId($item);
                $items[] = array_filter([
                    'id' => isset($item['product_id']) ? (string) $item['product_id'] : '',
                    'parent_id' => isset($item['parent_id']) ? (string) $item['parent_id'] : '',
                    'variant_id' => $variantId,
                    'sku' => isset($item['sku']) ? (string) $item['sku'] : '',
                    'name' => isset($item['title']) ? (string) $item['title'] : (string) ($item['name'] ?? ''),
                    'category' => (string) ($item['category'] ?? ''),
                    'quantity' => isset($item['quantity']) && is_numeric($item['quantity']) ? (float) $item['quantity'] : null,
                    'price' => isset($item['unit_price']) && is_numeric($item['unit_price'])
                        ? (float) $item['unit_price']
                        : (isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null),
                    'content_type' => $variantId !== '' ? 'product' : 'product_group',
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
        $metaCtx = isset($attrib['meta']) && is_array($attrib['meta']) ? $attrib['meta'] : [];

        if ($this->config->isAttributionEnabled($storeId)) {
            $window = $this->attributionWindow->readCurrent($storeId);
            if (!empty($window['first_touch']) && is_array($window['first_touch'])) {
                $first = array_merge($window['first_touch'], $first);
                $first = MatchingSignalFields::union($window['first_touch'], $first);
            }
            if (!empty($window['last_touch']) && is_array($window['last_touch'])) {
                $last = array_merge($window['last_touch'], $last);
                $last = MatchingSignalFields::union($window['last_touch'], $last);
            }
            if (!empty($window['meta']) && is_array($window['meta'])) {
                $metaCtx = array_merge($window['meta'], $metaCtx);
            }

            foreach ($this->requestCampaignTouch() as $mk => $val) {
                if (empty($first[$mk])) {
                    $first[$mk] = $val;
                }
                $last[$mk] = $val;
            }

            foreach (AttributionWindowCapture::ATTRIBUTION_KEYS as $mk) {
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

        if (!empty($data['attribution_snapshot']) && is_array($data['attribution_snapshot'])) {
            $snap = $data['attribution_snapshot'];
            if (!empty($snap['first_touch']) && is_array($snap['first_touch'])) {
                $first = array_merge($snap['first_touch'], $first);
                $first = MatchingSignalFields::union($snap['first_touch'], $first);
            }
            if (!empty($snap['last_touch']) && is_array($snap['last_touch'])) {
                $last = array_merge($snap['last_touch'], $last);
                $last = MatchingSignalFields::union($snap['last_touch'], $last);
            }
            if (!empty($snap['last_cookies']) && is_array($snap['last_cookies'])) {
                $last = MatchingSignalFields::union($last, $snap['last_cookies']);
            }
        }

        $attribution = [
            'first_touch' => $first === [] ? new \stdClass() : $first,
            'last_touch' => $last === [] ? new \stdClass() : $last,
        ];
        $meta = AttributionMetaBuilder::buildAttributionMeta(
            $first,
            $last,
            $metaCtx,
            $this->config->getAttributionLookbackDays($storeId)
        );
        if ($meta !== null) {
            $attribution['meta'] = $meta;
        }

        $marketing = ['attribution' => $attribution];
        $cookies = MatchingSignalFields::cookiesFromSources([$data, $last, $first]);
        if ($cookies !== []) {
            $marketing['cookies'] = $cookies;
        }

        return $marketing;
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
        foreach ([
            'fbp' => '_fbp',
            'fbc' => '_fbc',
            'ttp' => '_ttp',
            'epik' => '_epik',
            'scid' => '_scid',
            'twclid' => '_twclid',
            'oppref' => '__oppref',
            'uetmsclkid' => '_uetmsclkid',
        ] as $field => $cookieName) {
            if (!empty($data[$field])) {
                continue;
            }
            $raw = (string) $this->httpRequest->getCookie($cookieName, '');
            if ($raw !== '') {
                $data[$field] = $raw;
            }
        }

        $data = MatchingSignalFields::fillFromAttribution($data);

        // Synthesise fbc from fbclid query param when _fbc cookie is absent.
        if (empty($data['fbc'])) {
            $fbclid = (string) $this->httpRequest->getParam('fbclid', '');
            if ($fbclid !== '') {
                $ms = (int) floor(microtime(true) * 1000);
                $data['fbc'] = 'fb.1.' . $ms . '.' . $fbclid;
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
    private function buildExtensions(): array
    {
        $ext = [
            'magento' => [
                'module_version' => '1.2.10',
            ],
        ];
        $journeyId = $this->resolveJourneyId();
        if ($journeyId !== '') {
            $ext['debug'] = ['journey_id' => $journeyId];
        }
        return $ext;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildExtensionsFromData(array $data): array
    {
        $ext = $this->buildExtensions();
        if (!empty($data['browser_fingerprint']) && is_array($data['browser_fingerprint'])) {
            $ext['fingerprint'] = ['signals' => $data['browser_fingerprint']];
        }
        if (!empty($data['identity_link'])) {
            $trigger = isset($data['identity_trigger']) && is_string($data['identity_trigger'])
                ? $data['identity_trigger']
                : 'checkout_email';
            $ext['identity'] = [
                'action' => 'link',
                'trigger' => $trigger,
            ];
        }
        return $ext;
    }

    private function resolveJourneyId(): string
    {
        $param = (string) $this->httpRequest->getParam('panto_journey_id', '');
        if ($this->isUuidV4($param)) {
            return strtolower($param);
        }
        $cookie = (string) $this->httpRequest->getCookie('panto_journey_id', '');
        if ($this->isUuidV4($cookie)) {
            return strtolower($cookie);
        }
        return '';
    }

    private function isUuidV4(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            trim($value)
        );
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
        return MatchingSignalFields::firstValidIp([
            (string) $this->httpRequest->getServer('HTTP_CF_CONNECTING_IP', ''),
            (string) $this->httpRequest->getServer('HTTP_X_REAL_IP', ''),
            (string) $this->httpRequest->getServer('HTTP_X_FORWARDED_FOR', ''),
            (string) $this->httpRequest->getServer('REMOTE_ADDR', ''),
        ]);
    }

    /**
     * The cookie touch is written when the tracker posts this page's pageview, after server-side
     * emits on the same request (product_viewed); a campaign landing adds its own UTMs and click
     * ids here so those events do not report the previous page's touch (ATR-16).
     *
     * @return array<string, string>
     */
    private function requestCampaignTouch(): array
    {
        if ($this->httpRequest->isAjax()) {
            return [];
        }
        return SessionLandingPolicy::campaignTouch($this->httpRequest->getUriString());
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
