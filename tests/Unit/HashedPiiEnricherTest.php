<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Model\HashedPiiEnricher;
use Panto\Source\Model\HashedPiiHasher;

final class HashedPiiEnricherTest extends TestCase
{
    public function testReturnsUnchangedWhenHashedPiiDisabled(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('isHashedPiiEnabled')->with(1)->willReturn(false);
        $config->method('hasTrackingConsent')->willReturn(true);
        $enricher = new HashedPiiEnricher($config);
        $data = ['order_id' => 6];
        $this->assertSame($data, $enricher->enrich($this->order(), $data, 1));
    }

    public function testHashesWhenEnabledAndConsented(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('isHashedPiiEnabled')->with(1)->willReturn(true);
        $config->method('hasTrackingConsent')->with(1)->willReturn(true);
        $enricher = new HashedPiiEnricher($config);
        $out = $enricher->enrich($this->order(), [], 1);
        $this->assertArrayHasKey('hashed_user', $out);
        $this->assertSame(
            HashedPiiHasher::hash('manfred@example.com'),
            $out['hashed_user']['email_sha256']
        );
        $this->assertSame(
            hash('sha256', '13054920600'),
            $out['hashed_user']['phone_sha256']
        );
        $this->assertSame(HashedPiiHasher::hash('fl'), $out['hashed_user']['state_sha256']);
    }

    public function testShippingAddressFillsWhenBillingEmpty(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('isHashedPiiEnabled')->with(1)->willReturn(true);
        $config->method('hasTrackingConsent')->with(1)->willReturn(true);
        $enricher = new HashedPiiEnricher($config);
        $shipping = new class {
            public function getTelephone(): string
            {
                return '3055550100';
            }

            public function getFirstname(): string
            {
                return 'Ada';
            }

            public function getLastname(): string
            {
                return 'Lovelace';
            }

            public function getCity(): string
            {
                return 'Miami';
            }

            public function getRegion(): string
            {
                return 'Florida';
            }

            public function getRegionCode(): string
            {
                return 'FL';
            }

            public function getPostcode(): string
            {
                return '33101';
            }

            public function getCountryId(): string
            {
                return 'US';
            }
        };
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerEmail')->willReturn('ada@example.com');
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn($shipping);
        $out = $enricher->enrich($order, [], 1);
        $this->assertSame(HashedPiiHasher::hash('miami'), $out['hashed_user']['city_sha256']);
        $this->assertSame(HashedPiiHasher::hash('fl'), $out['hashed_user']['state_sha256']);
        $this->assertSame(hash('sha256', '13055550100'), $out['hashed_user']['phone_sha256']);
    }

    private function order(): OrderInterface
    {
        $billing = new class {
            public function getTelephone(): string
            {
                return '3054920600';
            }

            public function getFirstname(): string
            {
                return 'Manfred';
            }

            public function getLastname(): string
            {
                return 'Ekblad';
            }

            public function getCity(): string
            {
                return 'Miami';
            }

            public function getRegion(): string
            {
                return 'Florida';
            }

            public function getRegionCode(): string
            {
                return 'FL';
            }

            public function getPostcode(): string
            {
                return '33129';
            }

            public function getCountryId(): string
            {
                return 'US';
            }
        };
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerEmail')->willReturn('manfred@example.com');
        $order->method('getBillingAddress')->willReturn($billing);
        return $order;
    }
}
