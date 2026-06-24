<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Merges pantosource_browser_ctx cookie into event data (Woo parity).
 */
class BrowserContextBridge
{
    public const COOKIE_NAME = 'pantosource_browser_ctx';

    public function __construct(
        private readonly HttpRequest $httpRequest
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function mergeIntoData(array $data): array
    {
        $raw = (string) $this->httpRequest->getCookie(self::COOKIE_NAME, '');
        if ($raw === '') {
            return $data;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $data;
        }
        if (!isset($data['context']) || !is_array($data['context'])) {
            $data['context'] = [];
        }
        if (!empty($decoded['session']) && is_array($decoded['session'])) {
            $data['context']['session'] = array_merge(
                isset($data['context']['session']) && is_array($data['context']['session'])
                    ? $data['context']['session']
                    : [],
                $decoded['session']
            );
        }
        if (!empty($decoded['device']) && is_array($decoded['device'])) {
            $data['context']['device'] = array_merge(
                isset($data['context']['device']) && is_array($data['context']['device'])
                    ? $data['context']['device']
                    : [],
                $decoded['device']
            );
        }
        if (!empty($decoded['fingerprint']) && is_array($decoded['fingerprint'])) {
            $data['browser_fingerprint'] = $decoded['fingerprint'];
        }
        return $data;
    }
}
