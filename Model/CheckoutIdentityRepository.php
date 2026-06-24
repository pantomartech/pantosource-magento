<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Checkout\Model\Session as CheckoutSession;

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
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function buildSnapshot(array $data): array
    {
        $ctx = isset($data['context']) && is_array($data['context']) ? $data['context'] : [];
        $attrib = isset($ctx['attribution']) && is_array($ctx['attribution']) ? $ctx['attribution'] : [];
        return [
            'anonymous_id' => isset($data['anonymous_id']) ? (string) $data['anonymous_id'] : '',
            'session_id' => isset($data['session_id']) ? (string) $data['session_id'] : '',
            'attribution_snapshot' => [
                'first_touch' => $attrib['first_touch'] ?? [],
                'last_touch' => $attrib['last_touch'] ?? [],
            ],
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
                if (!isset($data['context']) || !is_array($data['context'])) {
                    $data['context'] = [];
                }
                if (!isset($data['context']['attribution']) || !is_array($data['context']['attribution'])) {
                    $data['context']['attribution'] = [];
                }
                if (!empty($decoded['first_touch'])) {
                    $data['context']['attribution']['first_touch'] = $decoded['first_touch'];
                }
                if (!empty($decoded['last_touch'])) {
                    $data['context']['attribution']['last_touch'] = $decoded['last_touch'];
                }
                $data['attribution_snapshot'] = [
                    'first_touch' => $decoded['first_touch'] ?? [],
                    'last_touch' => $decoded['last_touch'] ?? [],
                    'anonymous_id' => $data['anonymous_id'] ?? null,
                    'session_id' => $data['session_id'] ?? null,
                ];
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
