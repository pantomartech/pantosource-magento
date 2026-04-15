<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * Event metadata: required keys in $data before payload build (mirrors Woo registry).
 */
class EventRegistry
{
    /**
     * @return array<string, array{type:string, required_keys:list<string>, optional_keys:list<string>}>
     */
    public static function getRegistry(): array
    {
        return [
            'pageview' => [
                'type' => 'track',
                'required_keys' => [],
                'optional_keys' => ['context'],
            ],
            'product_viewed' => [
                'type' => 'track',
                'required_keys' => ['product_id'],
                'optional_keys' => ['product_name', 'price', 'currency', 'sku', 'context'],
            ],
            'product_list_viewed' => [
                'type' => 'track',
                'required_keys' => [],
                'optional_keys' => ['list_type', 'category_id', 'category_name', 'context'],
            ],
            'search_performed' => [
                'type' => 'track',
                'required_keys' => ['query'],
                'optional_keys' => ['result_count', 'context'],
            ],
            'magento_add_to_cart' => [
                'type' => 'track',
                'required_keys' => ['product_id', 'quantity'],
                'optional_keys' => ['sku', 'quote_item_id', 'context'],
            ],
            'magento_cart_item_quantity_changed' => [
                'type' => 'track',
                'required_keys' => ['quote_item_id', 'new_quantity', 'old_quantity', 'quantity_delta'],
                'optional_keys' => ['product_id', 'sku', 'context'],
            ],
            'magento_remove_from_cart' => [
                'type' => 'track',
                'required_keys' => ['quote_item_id'],
                'optional_keys' => ['product_id', 'quantity', 'sku', 'context'],
            ],
            'checkout_initiated' => [
                'type' => 'track',
                'required_keys' => [],
                'optional_keys' => ['cart_total', 'currency', 'item_count', 'items', 'context'],
            ],
            'magento_checkout_order_placed' => [
                'type' => 'track',
                'required_keys' => ['order_id'],
                'optional_keys' => ['order_increment_id', 'status', 'total', 'currency', 'items', 'customer_id', 'context'],
            ],
            'magento_purchase_completed' => [
                'type' => 'conversion',
                'required_keys' => ['order_id'],
                'optional_keys' => ['order_increment_id', 'status', 'total', 'currency', 'items', 'occurrence', 'customer_id', 'context'],
            ],
            'magento_order_fulfilled' => [
                'type' => 'track',
                'required_keys' => ['order_id'],
                'optional_keys' => ['shipment_id', 'context'],
            ],
            'magento_customer_signed_up' => [
                'type' => 'track',
                'required_keys' => ['customer_id'],
                'optional_keys' => ['email', 'context'],
            ],
            'magento_customer_logged_in' => [
                'type' => 'track',
                'required_keys' => ['customer_id', 'login_nonce'],
                'optional_keys' => ['email', 'context'],
            ],
            'magento_customer_logged_out' => [
                'type' => 'track',
                'required_keys' => ['customer_id', 'logout_nonce'],
                'optional_keys' => ['context'],
            ],
            'magento_customer_deleted' => [
                'type' => 'track',
                'required_keys' => ['customer_id'],
                'optional_keys' => ['context'],
            ],
        ];
    }

    public static function getSchema(string $eventName): array
    {
        $all = self::getRegistry();
        return $all[$eventName] ?? ['type' => 'track', 'required_keys' => [], 'optional_keys' => []];
    }

    /** @return list<string> */
    public static function getMissingRequired(string $eventName, array $data): array
    {
        $schema = self::getSchema($eventName);
        $missing = [];
        foreach ($schema['required_keys'] as $key) {
            if (!array_key_exists($key, $data)) {
                $missing[] = $key;
            }
        }
        return $missing;
    }
}
