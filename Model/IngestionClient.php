<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\HTTP\Client\Curl;
use Panto\Source\Api\ConfigInterface;
use Psr\Log\LoggerInterface;

class IngestionClient
{
    public function __construct(
        private readonly Curl $curl,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * POST JSON to ingestion. Returns [success, httpCode, message]
     *
     * @param array<string, mixed> $payload
     * @return array{0:bool,1:int,2:string}
     */
    public function send(array $payload, bool $blocking, ?int $storeId = null): array
    {
        $url = $this->config->getEventsEndpointUrl($storeId);
        if ($url === '') {
            return [false, 0, 'missing_base_url'];
        }
        $key = $this->config->getApiKey($storeId);
        if ($key === '') {
            return [false, 0, 'missing_api_key'];
        }

        $body = (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Authorization', 'Bearer ' . $key);
        $timeout = $blocking ? 30 : 3;
        $this->curl->setTimeout($timeout);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, min($timeout, 5));

        try {
            $this->curl->post($url, $body);
        } catch (\Throwable $e) {
            $this->logger->warning('PantoSource ingestion transport error: ' . $e->getMessage());
            return [false, 0, $e->getMessage()];
        }

        $status = (int) $this->curl->getStatus();
        // Duplicate event.id responses are idempotent success from ingestion.
        if (($status >= 200 && $status < 300) || $status === 409) {
            return [true, $status, $status === 409 ? 'duplicate_idempotent' : 'ok'];
        }
        return [false, $status, $this->curl->getBody()];
    }
}
