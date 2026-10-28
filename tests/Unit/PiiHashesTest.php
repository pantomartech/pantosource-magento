<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\HashedPiiHasher;
use Panto\Source\Model\PiiHashes;

/** Shared cases from current/pii-hash-vectors.json; keep fixtures/pii-hash-vectors.json identical. */
final class PiiHashesTest extends TestCase
{
    /** @return array<string, mixed> */
    private function vectors(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/fixtures/pii-hash-vectors.json'), true);
    }

    public function testEmailVectors(): void
    {
        foreach ($this->vectors()['email'] as $v) {
            $this->assertSame($v['hashes'] ?? [], PiiHashes::emailHashes($v['input']), $v['input']);
        }
    }

    public function testPhoneVectors(): void
    {
        foreach ($this->vectors()['phone'] as $v) {
            $countries = array_values(array_filter($v['countries'], 'is_string'));
            $this->assertSame($v['hashes'] ?? [], PiiHashes::phoneHashes($v['input'], $countries), $v['input']);
        }
    }

    public function testProfileHashesCarryEveryPlatformForm(): void
    {
        $out = HashedPiiHasher::fromProfile(
            [
                'phone' => '070-123 45 67',
                'shipping_address' => ['country_code' => 'SE'],
                'billing_address' => ['country_code' => 'US'],
            ],
            ' Jane.Doe+promo@Gmail.com '
        );
        $this->assertSame(hash('sha256', 'jane.doe+promo@gmail.com'), $out['email_sha256']);
        $this->assertSame(hash('sha256', 'janedoe@gmail.com'), $out['email_google_sha256']);
        $this->assertSame(hash('sha256', 'janedoe@gmail.com'), $out['email_microsoft_sha256']);
        $this->assertSame(hash('sha256', '46701234567'), $out['phone_sha256']);
        $this->assertSame(hash('sha256', '+46701234567'), $out['phone_e164_sha256']);
    }
}
