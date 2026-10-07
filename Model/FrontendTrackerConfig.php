<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Helper\ProductViewedClaim;
use Panto\Source\Helper\StorefrontPageClassifier;

/**
 * Builds storefront tracker JSON config (session-specific; served via async GET, not inline in FPC pages).
 */
class FrontendTrackerConfig
{
    public function __construct(
        private readonly FormKey $formKey,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly CurrentCatalogContext $catalogContext,
        private readonly HttpRequest $request,
        private readonly LayerResolver $layerResolver,
        private readonly CustomerSession $customerSession,
        private readonly UrlInterface $urlBuilder,
        private readonly StorefrontPageClassifier $pageClassifier,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductViewedClaim $productViewedClaim
    ) {
    }

    /**
     * @return array<string, mixed>|null Null when tracking is disabled or consent is not granted.
     */
    public function build(?int $storeId = null, ?string $pagePath = null): ?array
    {
        $store = $this->storeManager->getStore($storeId);
        $resolvedStoreId = (int) $store->getId();

        if (!$this->config->isEnabled($resolvedStoreId)
            || !$this->config->isFrontendTrackerEnabled($resolvedStoreId)
            || !$this->config->hasTrackingConsent($resolvedStoreId)) {
            return null;
        }

        $secure = (bool) $store->isUrlSecure();
        $classified = $this->pageClassifier->classify((string) ($pagePath ?? ''), $resolvedStoreId);

        $isProduct = $classified['isProduct'];
        $isCategoryList = $classified['isCategoryList'];
        $isSearch = $classified['isSearch'];
        $isCheckout = $classified['isCheckout'];
        $pageTypeHint = $classified['pageType'];

        $productData = null;
        $productIdForClaim = 0;
        if ($isProduct) {
            $product = $this->catalogContext->getCurrentProduct();
            if ($product === null && (int) $classified['productId'] > 0) {
                try {
                    $product = $this->productRepository->getById((int) $classified['productId'], false, $resolvedStoreId);
                } catch (\Throwable) {
                    $product = null;
                }
            }
            if ($product !== null && $product->getId()) {
                $productIdForClaim = (int) $product->getId();
                $price = method_exists($product, 'getFinalPrice')
                    ? (string) $product->getFinalPrice()
                    : (string) $product->getPrice();
                $productData = [
                    'id' => $productIdForClaim,
                    'name' => (string) $product->getName(),
                    'sku' => (string) $product->getSku(),
                    'price' => $price,
                ];
            } elseif ((int) $classified['productId'] > 0) {
                $productIdForClaim = (int) $classified['productId'];
            }
        }

        $categoryData = null;
        if ($isCategoryList) {
            $category = $this->catalogContext->getCurrentCategory();
            if ($category !== null) {
                $categoryData = [
                    'id' => (int) $category->getId(),
                    'name' => (string) $category->getName(),
                ];
            }
        }

        $searchQuery = $classified['searchQuery'];
        $resultCount = 0;
        if ($isSearch && $searchQuery === '') {
            $searchQuery = (string) $this->request->getParam('q');
        }
        if ($isSearch) {
            try {
                $layer = $this->layerResolver->get();
                $resultCount = (int) $layer->getProductCollection()->getSize();
            } catch (\Throwable) {
                $resultCount = 0;
            }
        }

        return [
            'eventUrl' => $this->urlBuilder->getUrl('pantosource/tracker/event', ['_secure' => $secure]),
            'markerUrl' => $this->urlBuilder->getUrl('pantosource/tracker/marker', ['_secure' => $secure]),
            'formKey' => $this->formKey->getFormKey(),
            'debug' => $this->config->isDebugEnabled($resolvedStoreId),
            'currency' => (string) $store->getCurrentCurrencyCode(),
            'flags' => [
                'isProduct' => $isProduct,
                'isCategoryList' => $isCategoryList,
                'isSearch' => $isSearch,
                'isCheckout' => $isCheckout,
                'pageTypeHint' => $pageTypeHint,
                'attributionEnabled' => $this->config->isAttributionEnabled($resolvedStoreId),
                'enableSpaPageviews' => $this->config->isSpaPageviewsEnabled($resolvedStoreId),
                'serverProductViewed' => $productIdForClaim > 0
                    && $this->productViewedClaim->isClaimed($productIdForClaim),
            ],
            'product' => $productData,
            'category' => $categoryData,
            'search' => [
                'query' => $searchQuery,
                'resultCount' => $resultCount,
            ],
            'customerId' => $this->customerSession->isLoggedIn()
                ? (int) $this->customerSession->getCustomerId()
                : 0,
        ];
    }
}
