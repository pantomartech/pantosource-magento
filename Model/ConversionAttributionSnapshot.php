<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * Attaches frozen attribution snapshot on conversion events.
 */
class ConversionAttributionSnapshot
{
    public function __construct(
        private readonly AttributionWindowCapture $attributionWindow
    ) {
    }

    /** @var list<string> */
    public const CONVERSION_EVENTS = [
        'magento_purchase_completed',
        'magento_checkout_order_placed',
        'magento_order_cancelled',
        'magento_refund_created',
        'add_payment_info',
    ];

    public function appliesTo(string $eventName): bool
    {
        return in_array($eventName, self::CONVERSION_EVENTS, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function attach(array $data, int $storeId): array
    {
        $window = $this->attributionWindow->readCurrent($storeId);
        $first = $window['first_touch'] ?? [];
        $last = $window['last_touch'] ?? [];
        if (!is_array($first)) {
            $first = [];
        }
        if (!is_array($last)) {
            $last = [];
        }
        if ($first === [] && $last === []) {
            return $data;
        }

        $clickIds = [];
        foreach (AttributionWindowCapture::ATTRIBUTION_KEYS as $key) {
            if (in_array($key, ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbp', 'fbc', 'ttp'], true)) {
                continue;
            }
            $v = $last[$key] ?? $first[$key] ?? null;
            if (is_string($v) && $v !== '') {
                $clickIds[$key] = $v;
            }
        }

        $data['attribution_snapshot'] = [
            'first_touch' => $first,
            'last_touch' => $last,
            'click_ids' => $clickIds,
            'anonymous_id' => $data['anonymous_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
        ];
        return $data;
    }
}
