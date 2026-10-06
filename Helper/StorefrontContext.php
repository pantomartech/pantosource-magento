<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

use Magento\Framework\App\Request\Http as HttpRequest;

class StorefrontContext
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(HttpRequest $request, string $pageType = '', string $title = ''): array
    {
        $path = (string) $request->getRequestUri();
        $tz = '';
        try {
            $tz = date_default_timezone_get();
        } catch (\Throwable) {
        }
        return [
            'timezone' => $tz,
            'path' => $path,
            'page_type' => $pageType,
            'referrer' => (string) $request->getServer('HTTP_REFERER', ''),
            'user_agent' => (string) $request->getServer('HTTP_USER_AGENT', ''),
            'page' => [
                'url' => '',
                'path' => $path,
                'title' => $title,
                'referrer' => (string) $request->getServer('HTTP_REFERER', ''),
                'type' => $pageType,
            ],
            'device' => [
                'platform' => '',
                'user_agent' => (string) $request->getServer('HTTP_USER_AGENT', ''),
            ],
        ];
    }

    /**
     * Stamp checkout success as the storefront page even when emit runs from invoice/admin.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function withCheckoutSuccessPage(array $context, string $successUrl): array
    {
        $url = trim($successUrl);
        $path = '/checkout/onepage/success/';
        if ($url !== '') {
            $parsedPath = parse_url($url, PHP_URL_PATH);
            if (is_string($parsedPath) && $parsedPath !== '') {
                $path = $parsedPath;
            }
            $query = parse_url($url, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                $path .= '?' . $query;
            }
        }
        $context['path'] = $path;
        if (!isset($context['page']) || !is_array($context['page'])) {
            $context['page'] = [];
        }
        $context['page']['url'] = $url;
        $context['page']['path'] = $path;
        return $context;
    }
}
