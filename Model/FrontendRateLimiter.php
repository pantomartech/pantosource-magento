<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Framework\App\CacheInterface;

class FrontendRateLimiter
{
    private const CACHE_PREFIX = 'pantosource_rate_';
    private const MAX_REQUESTS = 60;
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function isAllowed(string $clientKey): bool
    {
        $key = self::CACHE_PREFIX . md5($clientKey);
        $raw = $this->cache->load($key);
        $count = $raw !== false ? (int) $raw : 0;
        if ($count >= self::MAX_REQUESTS) {
            return false;
        }
        $this->cache->save((string) ($count + 1), $key, [], self::WINDOW_SECONDS);
        return true;
    }
}
