<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\EventRegistry;

final class OrderFulfilledRegistryTest extends TestCase
{
    public function testOrderFulfilledRegistered(): void
    {
        $registry = EventRegistry::getRegistry();
        $this->assertArrayHasKey('magento_order_fulfilled', $registry);
        $this->assertSame('track', $registry['magento_order_fulfilled']['type']);
    }
}
