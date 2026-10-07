<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Helper\ProductViewedClaim;
use Panto\Source\Helper\StorefrontPageClassifier;
use Panto\Source\Model\CurrentCatalogContext;
use Panto\Source\Model\FrontendTrackerConfig;

final class FrontendTrackerConfigProductLoadTest extends TestCase
{
    public function testLoadsProductByClassifierIdWhenRegistryEmpty(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(99);
        $product->method('getName')->willReturn('SPA Product');
        $product->method('getSku')->willReturn('spa-sku');
        $product->method('getPrice')->willReturn(12.5);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->expects($this->once())->method('getById')->with(99, false, 1)->willReturn($product);

        $classifier = $this->createMock(StorefrontPageClassifier::class);
        $classifier->method('classify')->willReturn([
            'pageType' => 'product',
            'isProduct' => true,
            'isCategoryList' => false,
            'isSearch' => false,
            'isCheckout' => false,
            'isCart' => false,
            'searchQuery' => '',
            'productId' => 99,
            'categoryId' => 0,
        ]);

        $catalog = $this->createMock(CurrentCatalogContext::class);
        $catalog->method('getCurrentProduct')->willReturn(null);

        $claim = $this->createMock(ProductViewedClaim::class);
        $claim->method('isClaimed')->with(99)->willReturn(false);

        $cfg = $this->buildConfig($classifier, $catalog, $repo, $claim);
        $out = $cfg->build(1, '/spa-product.html');

        $this->assertNotNull($out);
        $this->assertSame(99, $out['product']['id']);
        $this->assertSame('SPA Product', $out['product']['name']);
        $this->assertSame('spa-sku', $out['product']['sku']);
        $this->assertFalse($out['flags']['serverProductViewed']);
        $this->assertTrue($out['flags']['isProduct']);
    }

    public function testServerProductViewedFlagWhenClaimed(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(5);
        $product->method('getName')->willReturn('Claimed');
        $product->method('getSku')->willReturn('c');
        $product->method('getPrice')->willReturn(1);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('getById')->willReturn($product);

        $classifier = $this->createMock(StorefrontPageClassifier::class);
        $classifier->method('classify')->willReturn([
            'pageType' => 'product',
            'isProduct' => true,
            'isCategoryList' => false,
            'isSearch' => false,
            'isCheckout' => false,
            'isCart' => false,
            'searchQuery' => '',
            'productId' => 5,
            'categoryId' => 0,
        ]);

        $catalog = $this->createMock(CurrentCatalogContext::class);
        $catalog->method('getCurrentProduct')->willReturn(null);

        $claim = $this->createMock(ProductViewedClaim::class);
        $claim->method('isClaimed')->with(5)->willReturn(true);

        $cfg = $this->buildConfig($classifier, $catalog, $repo, $claim);
        $out = $cfg->build(1, '/claimed.html');

        $this->assertTrue($out['flags']['serverProductViewed']);
    }

    private function buildConfig(
        StorefrontPageClassifier $classifier,
        CurrentCatalogContext $catalog,
        ProductRepositoryInterface $repo,
        ProductViewedClaim $claim
    ): FrontendTrackerConfig {
        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('fk');

        $config = $this->createMock(ConfigInterface::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isFrontendTrackerEnabled')->willReturn(true);
        $config->method('hasTrackingConsent')->willReturn(true);
        $config->method('isDebugEnabled')->willReturn(false);
        $config->method('isAttributionEnabled')->willReturn(true);
        $config->method('isSpaPageviewsEnabled')->willReturn(true);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $store->method('isUrlSecure')->willReturn(true);
        $store->method('getCurrentCurrencyCode')->willReturn('USD');

        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        $request = $this->createMock(HttpRequest::class);
        $layer = $this->createMock(LayerResolver::class);
        $session = $this->createMock(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn(false);

        $urls = $this->createMock(UrlInterface::class);
        $urls->method('getUrl')->willReturnCallback(static function (string $path): string {
            return 'https://shop.test/' . $path;
        });

        return new FrontendTrackerConfig(
            $formKey,
            $config,
            $stores,
            $catalog,
            $request,
            $layer,
            $session,
            $urls,
            $classifier,
            $repo,
            $claim
        );
    }
}
