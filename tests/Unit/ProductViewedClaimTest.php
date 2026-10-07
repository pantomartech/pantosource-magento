<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Customer\Model\Session as CustomerSession;
use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\ProductViewedClaim;

final class ProductViewedClaimTest extends TestCase
{
    public function testSessionKeyFormat(): void
    {
        $this->assertSame('pantosource_product_viewed_42', ProductViewedClaim::sessionKey(42));
    }

    public function testClaimAndIsClaimed(): void
    {
        $store = [];
        $session = $this->createMock(CustomerSession::class);
        $session->method('setData')->willReturnCallback(static function (string $key, $value) use (&$store): void {
            $store[$key] = $value;
        });
        $session->method('getData')->willReturnCallback(static function (string $key) use (&$store) {
            return $store[$key] ?? null;
        });

        $claim = new ProductViewedClaim($session);
        $this->assertFalse($claim->isClaimed(7));
        $claim->claim(7);
        $this->assertTrue($claim->isClaimed(7));
        $this->assertFalse($claim->isClaimed(8));
        $claim->claim(0);
        $this->assertFalse($claim->isClaimed(0));
    }
}
