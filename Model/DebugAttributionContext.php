<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * Debug timeline attribution enrichment (Shopify debugAttributionContext parity).
 */
class DebugAttributionContext
{
    /** @var list<string> */
    private const CLICK_ID_KEYS = ['gclid', 'fbclid', 'ttclid', 'msclkid', 'gbraid', 'wbraid'];

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function buildContext(array $payload): array
    {
        $attrib = $this->extractAttribution($payload);
        $first = isset($attrib['first_touch']) && is_array($attrib['first_touch']) ? $attrib['first_touch'] : [];
        $last = isset($attrib['last_touch']) && is_array($attrib['last_touch']) ? $attrib['last_touch'] : [];
        $meta = isset($attrib['meta']) && is_array($attrib['meta']) ? $attrib['meta'] : [];

        $source = '';
        if (isset($meta['attribution_source']) && is_string($meta['attribution_source'])) {
            $source = $meta['attribution_source'];
        } else {
            $source = AttributionMetaBuilder::inferAttributionSource($last !== [] ? $last : $first);
        }

        $touchType = 'none';
        if ($last !== []) {
            $touchType = 'last_touch';
        } elseif ($first !== []) {
            $touchType = 'first_touch';
        }

        $clickIds = [];
        foreach (self::CLICK_ID_KEYS as $key) {
            $v = $last[$key] ?? $first[$key] ?? null;
            if (is_string($v) && $v !== '') {
                $clickIds[] = $key;
            }
        }

        return [
            'attribution_source' => $source,
            'touch_type' => $touchType,
            'click_ids_present' => $clickIds,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function rowMatchesFilters(array $row, string $attributionSource, string $touchType): bool
    {
        $ctx = isset($row['debug_context']) && is_array($row['debug_context']) ? $row['debug_context'] : [];
        if ($attributionSource !== '' && ($ctx['attribution_source'] ?? '') !== $attributionSource) {
            return false;
        }
        if ($touchType !== '' && ($ctx['touch_type'] ?? '') !== $touchType) {
            return false;
        }
        return true;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function extractAttribution(array $payload): array
    {
        if (isset($payload['marketing']['attribution']) && is_array($payload['marketing']['attribution'])) {
            return $payload['marketing']['attribution'];
        }
        if (isset($payload['context']['attribution']) && is_array($payload['context']['attribution'])) {
            return $payload['context']['attribution'];
        }
        return [];
    }
}
