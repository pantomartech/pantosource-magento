<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Resolves context.page.path for canonical payloads. Prefer JS/storefront path over the
 * current Magento request URI (tracker/event would otherwise leak into page.path).
 */
class CanonicalContextPath
{
    /**
     * @param array<string, mixed> $ctx
     */
    public static function resolve(array $ctx, string $requestUriFallback = ''): string
    {
        $path = (string) ($ctx['path'] ?? '');
        if ($path === '' && isset($ctx['page']) && is_array($ctx['page'])) {
            $path = (string) ($ctx['page']['path'] ?? '');
        }
        if ($path === '' && isset($ctx['page']) && is_array($ctx['page'])) {
            $pageUrl = (string) ($ctx['page']['url'] ?? '');
            if ($pageUrl !== '') {
                $path = self::pathFromUrl($pageUrl);
            }
        }
        if ($path === '' && $requestUriFallback !== '') {
            $path = self::pathFromUrl($requestUriFallback);
        }
        return $path;
    }

    private static function pathFromUrl(string $url): string
    {
        $p = parse_url($url, PHP_URL_PATH);
        $q = parse_url($url, PHP_URL_QUERY);
        return ($p ?: '/') . ($q ? '?' . $q : '');
    }
}
