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
}
