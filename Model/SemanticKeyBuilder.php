<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * Logical idempotency keys (aligned with Woo pantosource_build_semantic_key).
 */
class SemanticKeyBuilder
{
    public function build(string $eventName, array $data): string
    {
        if (isset($data['order_id']) && $data['order_id'] !== '' && $data['order_id'] !== null) {
            $phase = isset($data['occurrence']) ? (string) $data['occurrence'] : 'default';
            return 'order_' . $data['order_id'] . '_' . $eventName . '_' . preg_replace('/[^a-z0-9_\-]/i', '', $phase);
        }
        if (!empty($data['quote_item_id'])) {
            return 'cart_' . $data['quote_item_id'] . '_' . $eventName;
        }
        $stable = ['magento_customer_signed_up', 'magento_customer_deleted'];
        if (isset($data['customer_id']) && is_numeric($data['customer_id'])) {
            $cid = (int) $data['customer_id'];
            if ($cid > 0 && in_array($eventName, $stable, true)) {
                return 'customer_' . $cid . '_' . $eventName;
            }
            if ($cid > 0 && $eventName === 'magento_customer_logged_in' && !empty($data['login_nonce'])) {
                return 'customer_' . $cid . '_login_' . preg_replace('/[^a-z0-9_\-]/i', '', (string) $data['login_nonce']);
            }
            if ($cid > 0 && $eventName === 'magento_customer_logged_out' && !empty($data['logout_nonce'])) {
                return 'customer_' . $cid . '_logout_' . preg_replace('/[^a-z0-9_\-]/i', '', (string) $data['logout_nonce']);
            }
        }
        return $eventName . '_' . md5((string) json_encode($data));
    }
}
