<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\CanonicalContextPath;

final class CanonicalContextPathTest extends TestCase
{
    public function testPrefersNestedPagePathOverRequestFallback(): void
    {
        $path = CanonicalContextPath::resolve(
            [
                'page' => [
                    'path' => '/catalogsearch/result/?q=test',
                    'url' => 'https://magento-dev.pantosource.com/catalogsearch/result/?q=test',
                ],
            ],
            'https://magento-dev.pantosource.com/pantosource/tracker/event/?form_key=abc'
        );
        $this->assertSame('/catalogsearch/result/?q=test', $path);
    }

    public function testTopLevelPathWins(): void
    {
        $path = CanonicalContextPath::resolve(
            [
                'path' => '/checkout/cart/',
                'page' => ['path' => '/ignored'],
            ],
            'https://example.com/pantosource/tracker/event/'
        );
        $this->assertSame('/checkout/cart/', $path);
    }

    public function testFallsBackToPageUrlWhenPathMissing(): void
    {
        $path = CanonicalContextPath::resolve(
            ['page' => ['url' => 'https://store.example/test-product.html']],
            'https://store.example/pantosource/tracker/event/'
        );
        $this->assertSame('/test-product.html', $path);
    }
}
