<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Model\AttributionWindowCapture;

/**
 * Server-side attribution capture for observers (non-AJAX flows).
 */
class AttributionContext
{
    public function __construct(
        private readonly HttpRequest $httpRequest,
        private readonly AttributionWindowCapture $attributionWindow
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function mergeIntoData(array $data, int $storeId, string $shopHost): array
    {
        $incoming = $this->extractIncomingParams($data);
        $landing = '';
        $referrer = '';
        if (isset($data['context']['page']['url']) && is_string($data['context']['page']['url'])) {
            $landing = $data['context']['page']['url'];
        }
        if (isset($data['context']['page']['referrer']) && is_string($data['context']['page']['referrer'])) {
            $referrer = $data['context']['page']['referrer'];
        } elseif (isset($data['context']['referrer']) && is_string($data['context']['referrer'])) {
            $referrer = $data['context']['referrer'];
        }

        $captured = $this->attributionWindow->capture($storeId, $incoming, $shopHost, $landing, $referrer);
        if (!isset($data['context']) || !is_array($data['context'])) {
            $data['context'] = [];
        }
        $data['context']['attribution'] = [
            'first_touch' => $captured['first_touch'],
            'last_touch' => $captured['last_touch'],
            'meta' => $captured['meta'],
        ];
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function extractIncomingParams(array $data): array
    {
        $out = [];
        foreach (AttributionWindowCapture::ATTRIBUTION_KEYS as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                $out[$key] = trim($data[$key]);
                continue;
            }
            $fromReq = (string) $this->httpRequest->getParam($key, '');
            if ($fromReq !== '') {
                $out[$key] = $fromReq;
            }
        }
        if (!isset($out['sccid']) || $out['sccid'] === '') {
            $scCid = (string) $this->httpRequest->getParam('ScCid', '');
            if ($scCid !== '') {
                $out['sccid'] = $scCid;
            }
        }
        return $out;
    }
}
