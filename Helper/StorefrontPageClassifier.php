<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

use Magento\UrlRewrite\Model\UrlFinderInterface;

/**
 * Classifies a storefront URL for tracker config. Must not use the config request's action name.
 */
class StorefrontPageClassifier
{
    public const MAX_PATH_LENGTH = 2048;

    public function __construct(
        private readonly UrlFinderInterface $urlFinder
    ) {
    }

    /**
     * @return array{
     *   pageType: string,
     *   isProduct: bool,
     *   isCategoryList: bool,
     *   isSearch: bool,
     *   isCheckout: bool,
     *   isCart: bool,
     *   searchQuery: string,
     *   productId: int,
     *   categoryId: int
     * }
     */
    public function classify(string $pagePath, int $storeId): array
    {
        $parsed = self::parsePagePath($pagePath);
        if ($parsed === null) {
            return self::emptyResult();
        }

        $path = $parsed['path'];
        $query = $parsed['query'];

        if (str_contains($path, 'catalogsearch/result')) {
            $q = '';
            if ($query !== '') {
                parse_str($query, $params);
                $q = isset($params['q']) ? trim((string) $params['q']) : '';
            }
            return self::result('search', ['isSearch' => true, 'searchQuery' => $q]);
        }

        if ($path === '/checkout/cart' || str_starts_with($path, '/checkout/cart/')) {
            return self::result('cart', ['isCart' => true]);
        }

        if ($path === '/checkout' || $path === '/checkout/' || str_starts_with($path, '/checkout/')) {
            return self::result('checkout', ['isCheckout' => true]);
        }

        $rewrite = $this->resolveRewrite($path, $storeId);
        if ($rewrite !== null) {
            if (preg_match('#catalog/product/view/id/(\d+)#', $rewrite, $m) === 1) {
                return self::result('product', ['isProduct' => true, 'productId' => (int) $m[1]]);
            }
            if (preg_match('#catalog/category/view/id/(\d+)#', $rewrite, $m) === 1) {
                return self::result('category', ['isCategoryList' => true, 'categoryId' => (int) $m[1]]);
            }
        }

        return self::emptyResult();
    }

    /**
     * @return array{path: string, query: string}|null
     */
    public static function parsePagePath(string $pagePath): ?array
    {
        $raw = trim($pagePath);
        if ($raw === '' || strlen($raw) > self::MAX_PATH_LENGTH) {
            return null;
        }
        if (str_contains($raw, '://') || str_contains($raw, '..')) {
            return null;
        }
        if (!str_starts_with($raw, '/')) {
            return null;
        }

        $pathPart = $raw;
        $query = '';
        $qPos = strpos($raw, '?');
        if ($qPos !== false) {
            $pathPart = substr($raw, 0, $qPos);
            $query = substr($raw, $qPos + 1);
        }
        if ($pathPart === '') {
            $pathPart = '/';
        }

        return ['path' => $pathPart, 'query' => $query];
    }

    private function resolveRewrite(string $path, int $storeId): ?string
    {
        $requestPath = ltrim($path, '/');
        if ($requestPath === '') {
            return null;
        }
        try {
            $rewrite = $this->urlFinder->findOneByData([
                'request_path' => $requestPath,
                'store_id' => $storeId,
            ]);
        } catch (\Throwable) {
            return null;
        }
        if ($rewrite === null || !is_object($rewrite) || !method_exists($rewrite, 'getTargetPath')) {
            return null;
        }
        $target = (string) $rewrite->getTargetPath();
        return $target !== '' ? $target : null;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{
     *   pageType: string,
     *   isProduct: bool,
     *   isCategoryList: bool,
     *   isSearch: bool,
     *   isCheckout: bool,
     *   isCart: bool,
     *   searchQuery: string,
     *   productId: int,
     *   categoryId: int
     * }
     */
    private static function result(string $pageType, array $overrides = []): array
    {
        return array_merge(self::emptyResult(), ['pageType' => $pageType], $overrides);
    }

    /**
     * @return array{
     *   pageType: string,
     *   isProduct: bool,
     *   isCategoryList: bool,
     *   isSearch: bool,
     *   isCheckout: bool,
     *   isCart: bool,
     *   searchQuery: string,
     *   productId: int,
     *   categoryId: int
     * }
     */
    private static function emptyResult(): array
    {
        return [
            'pageType' => 'other',
            'isProduct' => false,
            'isCategoryList' => false,
            'isSearch' => false,
            'isCheckout' => false,
            'isCart' => false,
            'searchQuery' => '',
            'productId' => 0,
            'categoryId' => 0,
        ];
    }
}
