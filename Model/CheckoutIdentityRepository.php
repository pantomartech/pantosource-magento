<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Helper\CheckoutIdentitySnapshot;
use Panto\Source\Helper\MatchingSignalFields;

/**
 * Checkout identity stitching (Woo checkout_token / order meta parity).
 */
class CheckoutIdentityRepository
{
    private const TABLE = 'pantosource_order_identity';

    public const SESSION_ANON = 'pantosource_checkout_anonymous_id';
    public const SESSION_SESS = 'pantosource_checkout_session_id';
    public const SESSION_ATTR = 'pantosource_checkout_attribution_json';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CheckoutSession $checkoutSession,
        private readonly HttpRequest $httpRequest,
        private readonly AttributionWindowCapture $attributionWindow,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function buildSnapshot(array $data): array
    {
        $sid = (int) $this->storeManager->getStore()->getId();
        if ($this->config->hasTrackingConsent($sid) && $this->config->isAttributionEnabled($sid)) {
            $window = $this->attributionWindow->readCurrent($sid);
            if (!isset($data['context']) || !is_array($data['context'])) {
                $data['context'] = [];
            }
            $attrib = isset($data['context']['attribution']) && is_array($data['context']['attribution'])
                ? $data['context']['attribution']
                : [];
            $windowFirst = isset($window['first_touch']) && is_array($window['first_touch']) ? $window['first_touch'] : [];
            $windowLast = isset($window['last_touch']) && is_array($window['last_touch']) ? $window['last_touch'] : [];
            if (empty($attrib['first_touch']) && $windowFirst !== []) {
                $attrib['first_touch'] = $windowFirst;
            } elseif ($windowFirst !== [] && isset($attrib['first_touch']) && is_array($attrib['first_touch'])) {
                $attrib['first_touch'] = MatchingSignalFields::union($attrib['first_touch'], $windowFirst);
            }
            if (empty($attrib['last_touch']) && $windowLast !== []) {
                $attrib['last_touch'] = $windowLast;
            } elseif ($windowLast !== [] && isset($attrib['last_touch']) && is_array($attrib['last_touch'])) {
                $attrib['last_touch'] = MatchingSignalFields::union($attrib['last_touch'], $windowLast);
            }
            $data['context']['attribution'] = $attrib;
            foreach (['fbp' => '_fbp', 'fbc' => '_fbc', 'ttp' => '_ttp'] as $field => $cookieName) {
                if (!empty($data[$field])) {
                    continue;
                }
                $raw = (string) $this->httpRequest->getCookie($cookieName, '');
                if ($raw !== '') {
                    $data[$field] = $raw;
                }
            }
        }

        $ip = '';
        $ua = '';
        if ($this->config->hasTrackingConsent($sid)) {
            $ip = MatchingSignalFields::firstValidIp([
                (string) $this->httpRequest->getServer('HTTP_CF_CONNECTING_IP', ''),
                (string) $this->httpRequest->getServer('HTTP_X_REAL_IP', ''),
                (string) $this->httpRequest->getServer('HTTP_X_FORWARDED_FOR', ''),
                (string) $this->httpRequest->getServer('REMOTE_ADDR', ''),
            ]);
            $ctx = isset($data['context']) && is_array($data['context']) ? $data['context'] : [];
            $ua = MatchingSignalFields::nonempty($ctx['user_agent'] ?? '');
            if ($ua === '') {
                $ua = MatchingSignalFields::nonempty($this->httpRequest->getServer('HTTP_USER_AGENT', ''));
            }
        }

        $anon = isset($data['anonymous_id']) ? (string) $data['anonymous_id'] : '';
        $sess = isset($data['session_id']) ? (string) $data['session_id'] : '';
        if ($anon === '') {
            $anon = (string) $this->httpRequest->getCookie('panto_anonymous_id', '');
        }
        if ($sess === '') {
            $sess = (string) $this->httpRequest->getCookie('panto_session_id', '');
        }

        return [
            'anonymous_id' => $anon,
            'session_id' => $sess,
            'attribution_snapshot' => CheckoutIdentitySnapshot::fromEventData($data, $ip, $ua),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function persistToCheckoutSession(array $data): void
    {
        $snapshot = $this->buildSnapshot($data);
        if ($snapshot['anonymous_id'] !== '') {
            $this->checkoutSession->setData(self::SESSION_ANON, $snapshot['anonymous_id']);
        }
        if ($snapshot['session_id'] !== '') {
            $this->checkoutSession->setData(self::SESSION_SESS, $snapshot['session_id']);
        }
        $attrJson = json_encode($snapshot['attribution_snapshot']);
        if ($attrJson !== false && $attrJson !== '{}' && $attrJson !== '{"first_touch":[],"last_touch":[]}') {
            $this->checkoutSession->setData(self::SESSION_ATTR, $attrJson);
        }
    }

    public function copySessionToOrder(int $orderId): void
    {
        $anon = (string) $this->checkoutSession->getData(self::SESSION_ANON);
        $sess = (string) $this->checkoutSession->getData(self::SESSION_SESS);
        $attr = (string) $this->checkoutSession->getData(self::SESSION_ATTR);
        if ($anon === '' && $sess === '' && $attr === '') {
            return;
        }
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $conn->insertOnDuplicate($table, [
            'order_id' => $orderId,
            'anonymous_id' => $anon,
            'session_id' => $sess,
            'attribution_snapshot_json' => $attr,
            'created_at' => date('Y-m-d H:i:s'),
        ], ['anonymous_id', 'session_id', 'attribution_snapshot_json']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function resolveIdentityFromOrder(array $data, int $orderId): array
    {
        if ($orderId <= 0) {
            return $data;
        }
        $row = $this->loadByOrderId($orderId);
        if ($row === null) {
            return $data;
        }
        if (empty($data['anonymous_id']) && !empty($row['anonymous_id'])) {
            $data['anonymous_id'] = (string) $row['anonymous_id'];
        }
        if (empty($data['session_id']) && !empty($row['session_id'])) {
            $data['session_id'] = (string) $row['session_id'];
        }
        $attrJson = (string) ($row['attribution_snapshot_json'] ?? '');
        if ($attrJson !== '') {
            $decoded = json_decode($attrJson, true);
            if (is_array($decoded)) {
                $data = CheckoutIdentitySnapshot::applyToEventData($data, $decoded);
            }
        }
        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadByOrderId(int $orderId): ?array
    {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $row = $conn->fetchRow(
            $conn->select()->from($table)->where('order_id = ?', $orderId)
        );
        return is_array($row) ? $row : null;
    }
}
