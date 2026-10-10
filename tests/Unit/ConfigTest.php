<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Model\Config;

final class ConfigTest extends TestCase
{
    private const PLAIN_API_KEY = 'dummy0dummy0dummy0dummy0dummy0dummy0dummy0dummy0dummy0dummy0du';

    private const ENCRYPTED_API_KEY = '0:3:ny4VIgNwx5+sIJEGciphertextexample';

    public function testGetApiKeyReturnsEmptyWhenNotConfigured(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with(ConfigInterface::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->never())->method('decrypt');

        $config = new Config($scopeConfig, $encryptor);
        $this->assertSame('', $config->getApiKey(1));
    }

    public function testGetApiKeyDecryptsEncryptedScopeConfigValue(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with(ConfigInterface::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(self::ENCRYPTED_API_KEY);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())
            ->method('decrypt')
            ->with(self::ENCRYPTED_API_KEY)
            ->willReturn(self::PLAIN_API_KEY);

        $config = new Config($scopeConfig, $encryptor);
        $this->assertSame(self::PLAIN_API_KEY, $config->getApiKey());
    }

    public function testGetIngestionBaseUrlUsesProductionWhenUnset(): void
    {
        $config = $this->configWithBaseUrl('');
        $this->assertSame(Config::DEFAULT_INGESTION_BASE_URL, $config->getIngestionBaseUrl(1));
        $this->assertSame(Config::DEFAULT_INGESTION_BASE_URL . '/events', $config->getEventsEndpointUrl(1));
    }

    public function testGetIngestionBaseUrlRewritesStagingHost(): void
    {
        $config = $this->configWithBaseUrl('http://events.staging.pantosource.com/');
        $this->assertSame(Config::DEFAULT_INGESTION_BASE_URL, $config->getIngestionBaseUrl());
    }

    public function testGetIngestionBaseUrlKeepsCustomEndpoint(): void
    {
        $config = $this->configWithBaseUrl('https://ingest.example.com/');
        $this->assertSame('https://ingest.example.com', $config->getIngestionBaseUrl());
        $this->assertSame('https://ingest.example.com/events', $config->getEventsEndpointUrl());
    }

    private function configWithBaseUrl(string $baseUrl): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with(ConfigInterface::XML_PATH_BASE_URL, ScopeInterface::SCOPE_STORE, $this->anything())
            ->willReturn($baseUrl);

        return new Config($scopeConfig, $this->createStub(EncryptorInterface::class));
    }
}
