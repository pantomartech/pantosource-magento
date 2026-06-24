<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\CheckoutIdentityRepository;

final class CheckoutIdentityRepositoryTest extends TestCase
{
    public function testSessionKeysAreStable(): void
    {
        $this->assertSame('pantosource_checkout_anonymous_id', CheckoutIdentityRepository::SESSION_ANON);
        $this->assertSame('pantosource_checkout_session_id', CheckoutIdentityRepository::SESSION_SESS);
        $this->assertSame('pantosource_checkout_attribution_json', CheckoutIdentityRepository::SESSION_ATTR);
    }
}
