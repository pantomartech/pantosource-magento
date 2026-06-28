<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;

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
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @return array<string, mixed>|null Null when tracking is disabled or consent is not granted.
     */
    public function build(?int $storeId = null): ?array
    {
        $store = $this->storeManager->getStore($storeId);
        $resolvedStoreId = (int) $store->getId();

        if (!$this->config->isEnabled($resolvedStoreId)
            || !$this->config->isFrontendTrackerEnabled($resolvedStoreId)
            || !$this->config->hasTrackingConsent($resolvedStoreId)) {
            return null;
        }

        $secure = (bool) $store->isUrlSecure();
        $fan = (string) $this->request->getFullActionName();

        $isProduct = $fan === 'catalog_product_view';
        $isCategoryList = $fan === 'catalog_category_view';
        $isSearch = $fan === 'catalogsearch_result_index';
        $isCheckout = $fan === 'checkout_index_index';

        $productData = null;
        if ($isProduct) {
            $product = $this->catalogContext->getCurrentProduct();
            if ($product !== null) {
                $productData = [
                    'id' => (int) $product->getId(),
                    'name' => (string) $product->getName(),
                    'sku' => (string) $product->getSku(),
                    'price' => (string) $product->getFinalPrice(),
                ];
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

        $searchQuery = '';
        $resultCount = 0;
        if ($isSearch) {
            $searchQuery = (string) $this->request->getParam('q');
            try {
                $layer = $this->layerResolver->get();
                $resultCount = (int) $layer->getProductCollection()->getSize();
            } catch (\Throwable) {
                $resultCount = 0;
            }
        }

        $pageTypeHint = 'other';
        if ($isProduct) {
            $pageTypeHint = 'product';
        } elseif ($isCategoryList) {
            $pageTypeHint = 'category';
        } elseif ($isSearch) {
            $pageTypeHint = 'search';
        } elseif ($isCheckout) {
            $pageTypeHint = 'checkout';
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
