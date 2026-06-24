<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\QueueBroker;

final class QueueBrokerLeasingTest extends TestCase
{
    public function testLeaseSecondsConstant(): void
    {
        $this->assertSame(60, QueueBroker::LEASE_SECONDS);
    }
}
