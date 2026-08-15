<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\UrlRewrite\Model\UrlFinderInterface;
use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\StorefrontPageClassifier;

final class StorefrontPageClassifierTest extends TestCase
{
    public function testSearchPathExtractsQuery(): void
    {
        $classified = $this->classifier()->classify('/catalogsearch/result/?q=test', 1);
        $this->assertSame('search', $classified['pageType']);
        $this->assertTrue($classified['isSearch']);
        $this->assertSame('test', $classified['searchQuery']);
    }

    public function testCartAndCheckoutPaths(): void
    {
        $cart = $this->classifier()->classify('/checkout/cart/', 1);
        $this->assertSame('cart', $cart['pageType']);
        $this->assertTrue($cart['isCart']);
        $this->assertFalse($cart['isCheckout']);

        $checkout = $this->classifier()->classify('/checkout/', 1);
        $this->assertSame('checkout', $checkout['pageType']);
        $this->assertTrue($checkout['isCheckout']);
        $this->assertFalse($checkout['isCart']);
    }

    public function testProductAndCategoryRewrites(): void
    {
        $finder = $this->createMock(UrlFinderInterface::class);
        $finder->method('findOneByData')->willReturnCallback(static function (array $data) {
            $path = (string) ($data['request_path'] ?? '');
            if ($path === 'test-product.html') {
                return new class {
                    public function getTargetPath(): string
                    {
                        return 'catalog/product/view/id/1';
                    }
                };
            }
            if ($path === 'default-category.html') {
                return new class {
                    public function getTargetPath(): string
                    {
                        return 'catalog/category/view/id/2';
                    }
                };
            }
            return null;
        });

        $classifier = new StorefrontPageClassifier($finder);
        $product = $classifier->classify('/test-product.html', 1);
        $this->assertSame('product', $product['pageType']);
        $this->assertTrue($product['isProduct']);
        $this->assertSame(1, $product['productId']);

        $category = $classifier->classify('/default-category.html', 1);
        $this->assertSame('category', $category['pageType']);
        $this->assertTrue($category['isCategoryList']);
        $this->assertSame(2, $category['categoryId']);
    }

    public function testRejectsUnsafeAndUnknownPaths(): void
    {
        $classifier = $this->classifier();
        $this->assertSame('other', $classifier->classify('https://evil.example/x', 1)['pageType']);
        $this->assertSame('other', $classifier->classify('/foo/../admin', 1)['pageType']);
        $this->assertSame('other', $classifier->classify('catalogsearch/result/?q=x', 1)['pageType']);
        $this->assertSame('other', $classifier->classify('/unknown-page', 1)['pageType']);
        $this->assertNull(StorefrontPageClassifier::parsePagePath(str_repeat('a', 2049)));
    }

    private function classifier(): StorefrontPageClassifier
    {
        $finder = $this->createMock(UrlFinderInterface::class);
        $finder->method('findOneByData')->willReturn(null);
        return new StorefrontPageClassifier($finder);
    }
}
