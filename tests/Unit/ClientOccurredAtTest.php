<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Helper\ClientOccurredAt;

final class ClientOccurredAtTest extends TestCase
{
    public function testAcceptsRecentIsoTimestamp(): void
    {
        $now = time();
        $iso = gmdate('Y-m-d\TH:i:s', $now - 60) . '.000Z';
        $this->assertSame($iso, ClientOccurredAt::normalize($iso, $now));
    }

    public function testRejectsFutureBeyondSkew(): void
    {
        $now = time();
        $future = gmdate('Y-m-d\TH:i:s', $now + 8 * 3600) . '.000Z';
        $this->assertSame('', ClientOccurredAt::normalize($future, $now));
    }

    public function testRejectsAncientTimestamp(): void
    {
        $now = time();
        $old = gmdate('Y-m-d\TH:i:s', $now - 40 * 86400) . '.000Z';
        $this->assertSame('', ClientOccurredAt::normalize($old, $now));
    }

    public function testRejectsGarbage(): void
    {
        $this->assertSame('', ClientOccurredAt::normalize('not-a-date'));
        $this->assertSame('', ClientOccurredAt::normalize(null));
        $this->assertSame('', ClientOccurredAt::normalize(''));
    }
}
