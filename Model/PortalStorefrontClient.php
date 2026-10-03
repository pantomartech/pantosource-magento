<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\HTTP\Client\Curl;
use Panto\Source\Api\ConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Store-health order snapshot upload to portal-integration (SF-1 / SF-2).
 */
class PortalStorefrontClient
{
    private const INTEGRATION = 'magento';
    private const MODULE_VERSION = '1.2.6';

    public function __construct(
        private readonly Curl $curl,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{0: bool, 1: int, 2: array<string, mixed>|null, 3: string}
     */
    public function getSyncState(int $storeId): array
    {
        $url = $this->orderSnapshotsUrl($storeId, '/sync-state');
        if ($url === '') {
            return [false, 0, null, 'missing_portal_or_api_key'];
        }

        return $this->request('GET', $url, null, $storeId);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{0: bool, 1: int, 2: array<string, mixed>|null, 3: string}
     */
    public function uploadOrderSnapshots(int $storeId, array $body): array
    {
        $url = $this->orderSnapshotsUrl($storeId, '');
        if ($url === '') {
            return [false, 0, null, 'missing_portal_or_api_key'];
        }

        return $this->request('POST', $url, $body, $storeId);
    }

    private function orderSnapshotsUrl(int $storeId, string $suffix): string
    {
        $base = $this->config->getPortalBaseUrl($storeId);
        if ($base === '' || $this->config->getApiKey($storeId) === '') {
            return '';
        }

        return $base . '/api/v1/storefront/order-snapshots' . $suffix;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{0: bool, 1: int, 2: array<string, mixed>|null, 3: string}
     */
    private function request(string $method, string $url, ?array $body, int $storeId): array
    {
        $key = $this->config->getApiKey($storeId);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Authorization', 'Bearer ' . $key);
        $this->curl->addHeader('X-Panto-Integration', self::INTEGRATION);
        $this->curl->addHeader('X-Panto-Integration-Version', self::MODULE_VERSION);
        $this->curl->setTimeout(60);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);

        try {
            if ($method === 'GET') {
                $this->curl->get($url);
            } else {
                $json = $body !== null
                    ? (string) json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
                    : '{}';
                $this->curl->post($url, $json);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('PantoSource portal snapshot transport error: ' . $e->getMessage());
            return [false, 0, null, $e->getMessage()];
        }

        $status = (int) $this->curl->getStatus();
        $raw = (string) $this->curl->getBody();
        $decoded = null;
        if ($raw !== '') {
            try {
                $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($parsed)) {
                    $decoded = $parsed;
                }
            } catch (\Throwable) {
                $decoded = null;
            }
        }

        $ok = $status >= 200 && $status < 300;
        return [$ok, $status, $decoded, $raw !== '' ? $raw : (string) $status];
    }
}
