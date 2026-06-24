<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\EventRegistry;

final class CustomerUpdatedRegistryTest extends TestCase
{
    public function testCustomerUpdatedRegisteredAsIdentify(): void
    {
        $registry = EventRegistry::getRegistry();
        $this->assertArrayHasKey('magento_customer_updated', $registry);
        $this->assertSame('identify', $registry['magento_customer_updated']['type']);
    }
}
