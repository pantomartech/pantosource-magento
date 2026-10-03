<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\OrderPlacementChannel;

final class OrderPlacementChannelTest extends TestCase
{
    public function testShouldRecordFrontendAndGraphql(): void
    {
        $this->assertTrue(OrderPlacementChannel::shouldRecordAtPlacement('frontend', false));
        $this->assertTrue(OrderPlacementChannel::shouldRecordAtPlacement('graphql', false));
    }

    public function testWebapiRestRequiresCartToken(): void
    {
        $this->assertFalse(OrderPlacementChannel::shouldRecordAtPlacement('webapi_rest', false));
        $this->assertTrue(OrderPlacementChannel::shouldRecordAtPlacement('webapi_rest', true));
    }

    public function testAdminhtmlNotRecorded(): void
    {
        $this->assertFalse(OrderPlacementChannel::shouldRecordAtPlacement('adminhtml', true));
    }

    public function testSnapshotChannelOnlineFlag(): void
    {
        [$channel, $online] = OrderPlacementChannel::snapshotChannel('frontend');
        $this->assertSame('frontend', $channel);
        $this->assertTrue($online);

        [, $offline] = OrderPlacementChannel::snapshotChannel(null);
        $this->assertFalse($offline);
    }
}
