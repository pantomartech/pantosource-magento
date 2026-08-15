<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Framework\App\Request\Http as HttpRequest;
use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\CartAddEmissionGate;

final class CartAddEmissionGateTest extends TestCase
{
    public function testSecondCallWithSameItemIdIsSuppressed(): void
    {
        $gate = new CartAddEmissionGate($this->requestBag());
        $key = CartAddEmissionGate::dedupeKey(9, 7, 1, 1.0);
        $this->assertTrue($gate->shouldEmit($key));
        $this->assertFalse($gate->shouldEmit($key));
    }

    public function testFallbackKeyWhenItemIdMissing(): void
    {
        $key = CartAddEmissionGate::dedupeKey(0, 7, 1, 2.0);
        $this->assertSame('qp:7:1:2', $key);
        $gate = new CartAddEmissionGate($this->requestBag());
        $this->assertTrue($gate->shouldEmit($key));
        $this->assertFalse($gate->shouldEmit($key));
    }

    public function testEmptyKeyDoesNotEmit(): void
    {
        $this->assertSame('', CartAddEmissionGate::dedupeKey(0, 0, 0, 0.0));
        $gate = new CartAddEmissionGate($this->requestBag());
        $this->assertFalse($gate->shouldEmit(''));
    }

    private function requestBag(): HttpRequest
    {
        $store = [];
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(
            static function ($key, $default = null) use (&$store) {
                return $store[$key] ?? $default;
            }
        );
        $request->method('setParam')->willReturnCallback(
            static function ($key, $value) use (&$store, $request) {
                $store[$key] = $value;
                return $request;
            }
        );
        return $request;
    }
}
