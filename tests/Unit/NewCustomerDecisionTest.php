<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\NewCustomerDecision;

final class NewCustomerDecisionTest extends TestCase
{
    public function testRegisteredFirstOrderIsNew(): void
    {
        $this->assertTrue(NewCustomerDecision::isNew(10, 1, 0));
    }

    public function testRegisteredSecondOrderIsNotNew(): void
    {
        $this->assertFalse(NewCustomerDecision::isNew(10, 2, 0));
    }

    public function testGuestFirstEmailOrderIsNew(): void
    {
        $this->assertTrue(NewCustomerDecision::isNew(0, 0, 1));
    }

    public function testGuestReturningEmailIsNotNew(): void
    {
        $this->assertFalse(NewCustomerDecision::isNew(0, 0, 2));
    }
}
