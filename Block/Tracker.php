<?php
declare(strict_types=1);

namespace Panto\Source\Block;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;

class Tracker extends Template
{
    public function __construct(
        Context $context,
        private readonly FormKey $formKey,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly Registry $registry,
        private readonly HttpRequest $request,
        private readonly LayerResolver $layerResolver,
        private readonly Json $jsonSerializer,
        private readonly CustomerSession $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)
            || !$this->config->isFrontendTrackerEnabled($storeId)
            || !$this->config->hasTrackingConsent($storeId)) {
            return '';
        }
        return parent::_toHtml();
    }

    public function getConfigJson(): string
    {
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        $fan = (string) $this->request->getFullActionName();

        $isProduct = $fan === 'catalog_product_view';
        $isCategoryList = $fan === 'catalog_category_view';
        $isSearch = $fan === 'catalogsearch_result_index';
        $isCheckout = $fan === 'checkout_index_index';

        $productData = null;
        if ($isProduct) {
            $product = $this->registry->registry('current_product');
            if ($product && $product->getId()) {
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
            $category = $this->registry->registry('current_category');
            if ($category && $category->getId()) {
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

        $cfg = [
            'eventUrl' => $this->getUrl('pantosource/tracker/event', ['_secure' => $store->isUrlSecure()]),
            'markerUrl' => $this->getUrl('pantosource/tracker/marker', ['_secure' => $store->isUrlSecure()]),
            'formKey' => $this->formKey->getFormKey(),
            'debug' => $this->config->isDebugEnabled($storeId),
            'currency' => (string) $store->getCurrentCurrencyCode(),
            'flags' => [
                'isProduct' => $isProduct,
                'isCategoryList' => $isCategoryList,
                'isSearch' => $isSearch,
                'isCheckout' => $isCheckout,
                'pageTypeHint' => $pageTypeHint,
                'attributionEnabled' => $this->config->isAttributionEnabled($storeId),
                'enableSpaPageviews' => $this->config->isSpaPageviewsEnabled($storeId),
            ],
            'product' => $productData,
            'category' => $categoryData,
            'search' => [
                'query' => $searchQuery,
                'resultCount' => $resultCount,
            ],
            'customerId' => $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : 0,
        ];

        return $this->jsonSerializer->serialize($cfg);
    }
}
