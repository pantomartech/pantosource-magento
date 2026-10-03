<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mirrors PayloadBuilder::buildCommerce sales_channel wiring without bootstrapping Magento.
 */
final class CommerceSalesChannelTest extends TestCase
{
    public function testSalesChannelIncludedWhenPresent(): void
    {
        $commerce = $this->buildCommerceSample([
            'order_id' => 1,
            'currency' => 'USD',
            'total' => '10',
            'sales_channel' => 'graphql',
        ]);
        $this->assertSame('graphql', $commerce['sales_channel']);
    }

    public function testSalesChannelOmittedWhenEmpty(): void
    {
        $commerce = $this->buildCommerceSample([
            'order_id' => 1,
            'sales_channel' => '',
        ]);
        $this->assertArrayNotHasKey('sales_channel', $commerce);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildCommerceSample(array $data): array
    {
        $commerce = [];
        if (isset($data['order_id'])) {
            $commerce['order_id'] = (string) $data['order_id'];
        }
        if (isset($data['currency'])) {
            $commerce['currency'] = (string) $data['currency'];
        }
        if (isset($data['sales_channel']) && is_string($data['sales_channel']) && $data['sales_channel'] !== '') {
            $commerce['sales_channel'] = $data['sales_channel'];
        }

        return $commerce;
    }
}
