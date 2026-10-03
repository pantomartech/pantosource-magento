<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\OrderSnapshotMapper;

final class OrderSnapshotMapperTest extends TestCase
{
    public function testMapsOrderWithOnlineChannel(): void
    {
        $snapshot = OrderSnapshotMapper::toSnapshot([
            'entity_id' => 99,
            'increment_id' => '100000099',
            'created_at' => '2026-10-01 12:00:00',
            'updated_at' => '2026-10-01 12:05:00',
            'status' => 'processing',
            'state' => 'processing',
            'order_currency_code' => 'USD',
            'grand_total' => 120.5,
            'subtotal' => 100,
            'tax_amount' => 10,
            'shipping_amount' => 5,
            'discount_amount' => 0,
            'total_refunded' => 0,
        ], 'frontend', true);

        $this->assertSame('99', $snapshot['order_id']);
        $this->assertTrue($snapshot['purchase_event_sent']);
        $this->assertSame('frontend', $snapshot['channel']);
        $this->assertTrue($snapshot['is_online_store']);
        $this->assertSame('120.5000', $snapshot['total']);
        $this->assertFalse($snapshot['is_test']);
    }

    public function testTombstoneShape(): void
    {
        $row = OrderSnapshotMapper::tombstone(42, '2026-10-02 08:00:00');
        $this->assertSame('42', $row['order_id']);
        $this->assertTrue($row['deleted']);
    }
}
