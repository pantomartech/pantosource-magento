<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\HashedPiiHasher;

final class HashedPiiHasherTest extends TestCase
{
    public function testHashesEmailLowerTrimmed(): void
    {
        $out = HashedPiiHasher::hashFields(['email' => '  Foo@Example.COM ']);
        $this->assertSame(hash('sha256', 'foo@example.com'), $out['email_sha256']);
    }

    public function testPhoneDigitsOnlyThenHash(): void
    {
        $out = HashedPiiHasher::hashFields(['phone' => '+1 (555) 010-1234']);
        $this->assertSame(hash('sha256', '15550101234'), $out['phone_sha256']);
    }

    public function testAddressHashes(): void
    {
        $out = HashedPiiHasher::hashFields([
            'city' => 'Austin',
            'state' => 'TX',
            'zip' => '78701',
            'country' => 'US',
        ]);
        $this->assertSame(hash('sha256', 'austin'), $out['city_sha256']);
        $this->assertSame(hash('sha256', 'tx'), $out['state_sha256']);
        $this->assertSame(hash('sha256', '78701'), $out['zip_sha256']);
        $this->assertSame(hash('sha256', 'us'), $out['country_sha256']);
    }

    public function testEmptyFieldsOmitted(): void
    {
        $out = HashedPiiHasher::hashFields(['email' => '', 'city' => '  ']);
        $this->assertSame([], $out);
    }
}
