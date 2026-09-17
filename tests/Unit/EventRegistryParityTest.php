<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\ConversionAttributionSnapshot;
use Panto\Source\Model\EventRegistry;

final class EventRegistryParityTest extends TestCase
{
    public function testCustomerIdentifiedIsIdentify(): void
    {
        $registry = EventRegistry::getRegistry();
        $this->assertArrayHasKey('magento_customer_identified', $registry);
        $this->assertSame('identify', $registry['magento_customer_identified']['type']);
        $this->assertSame([], $registry['magento_customer_identified']['required_keys']);
        $this->assertContains('email', $registry['magento_customer_identified']['optional_keys']);
    }

    public function testAddToCartOptionalLineKeys(): void
    {
        $opt = EventRegistry::getRegistry()['magento_add_to_cart']['optional_keys'];
        $this->assertContains('title', $opt);
        $this->assertContains('items', $opt);
        $this->assertContains('cart_id', $opt);
        $this->assertContains('unit_price', $opt);
    }

    public function testWishlistIdOptional(): void
    {
        $opt = EventRegistry::getRegistry()['magento_add_to_wishlist']['optional_keys'];
        $this->assertContains('wishlist_id', $opt);
    }

    public function testPaymentInfoInAttributionSnapshotList(): void
    {
        $this->assertContains('add_payment_info', ConversionAttributionSnapshot::CONVERSION_EVENTS);
    }
}
