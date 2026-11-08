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
        $key = CartAddEmissionGate::dedupeKey(9);
        $this->assertSame('item:9', $key);
        $this->assertTrue($gate->shouldEmit($key));
        $this->assertFalse($gate->shouldEmit($key));
    }

    public function testUnsavedItemHasNoKeyAndDoesNotEmit(): void
    {
        $this->assertSame('', CartAddEmissionGate::dedupeKey(0));
        $gate = new CartAddEmissionGate($this->requestBag());
        $this->assertFalse($gate->shouldEmit(''));
    }

    public function testTakePendingReturnsTrueOnceForDeferredItem(): void
    {
        $gate = new CartAddEmissionGate($this->requestBag());
        $item = new \stdClass();
        $gate->defer($item);
        $gate->defer($item);
        $this->assertFalse($gate->takePending(new \stdClass()));
        $this->assertTrue($gate->takePending($item));
        $this->assertFalse($gate->takePending($item));
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
