<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Customer\Model\Session as CustomerSession;
use PHPUnit\Framework\TestCase;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Model\LastKnownIdentityStore;

final class LastKnownIdentityStoreTest extends TestCase
{
    public function testSessionKeyIsStable(): void
    {
        $this->assertSame('pantosource_last_known_identity', LastKnownIdentityStore::SESSION_KEY);
    }

    public function testGuestPageviewDoesNotPickUpPurchaseSnapshot(): void
    {
        $session = $this->createMock(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn(false);
        $session->method('getData')->with(LastKnownIdentityStore::SESSION_KEY)->willReturn(json_encode([
            'email' => 'buyer@example.com',
            'external_id' => '42',
            'profile' => ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '+15551212'],
        ]));
        $store = new LastKnownIdentityStore($session, $this->consent(true));
        $out = $store->applyToData([], 1);
        $this->assertArrayNotHasKey('email', $out);
        $this->assertArrayNotHasKey('customer_id', $out);
        $this->assertArrayNotHasKey('customer_profile', $out);
    }

    public function testLoggedInPageviewPicksUpPurchaseSnapshot(): void
    {
        $session = $this->createMock(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn(true);
        $session->method('getData')->with(LastKnownIdentityStore::SESSION_KEY)->willReturn(json_encode([
            'email' => 'buyer@example.com',
            'external_id' => '42',
            'profile' => ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '+15551212'],
        ]));
        $store = new LastKnownIdentityStore($session, $this->consent(true));
        $out = $store->applyToData([], 1);
        $this->assertSame('buyer@example.com', $out['email']);
        $this->assertSame(42, $out['customer_id']);
        $this->assertSame('Ada', $out['customer_profile']['first_name']);
    }

    public function testDoesNotOverwritePresentKeys(): void
    {
        $session = $this->createMock(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn(true);
        $session->method('getData')->with(LastKnownIdentityStore::SESSION_KEY)->willReturn(json_encode([
            'email' => 'stitched@example.com',
            'profile' => ['first_name' => 'Stitched', 'last_name' => 'Other'],
        ]));
        $store = new LastKnownIdentityStore($session, $this->consent(true));
        $out = $store->applyToData([
            'email' => 'live@example.com',
            'customer_profile' => ['first_name' => 'Live'],
        ], 1);
        $this->assertSame('live@example.com', $out['email']);
        $this->assertSame('Live', $out['customer_profile']['first_name']);
        $this->assertSame('Other', $out['customer_profile']['last_name']);
    }

    public function testConsentOffDoesNotPersistOrApply(): void
    {
        $session = $this->createMock(CustomerSession::class);
        $session->expects($this->never())->method('setData');
        $session->expects($this->never())->method('getData');
        $store = new LastKnownIdentityStore($session, $this->consent(false));
        $this->assertSame(['email' => 'x@y.com'], $store->applyToData(['email' => 'x@y.com'], 1));
        $store->persistFromData(['email' => 'x@y.com', 'customer_profile' => ['first_name' => 'Ada']], 1);
    }

    public function testPersistWritesUnionOnCustomerSession(): void
    {
        $session = $this->createMock(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn(true);
        $session->method('getData')->with(LastKnownIdentityStore::SESSION_KEY)->willReturn(json_encode([
            'email' => 'old@example.com',
            'profile' => ['first_name' => 'Old', 'city' => 'London'],
        ]));
        $session->expects($this->once())->method('setData')->with(
            LastKnownIdentityStore::SESSION_KEY,
            $this->callback(function (string $json): bool {
                $decoded = json_decode($json, true);
                return $decoded['email'] === 'new@example.com'
                    && $decoded['profile']['first_name'] === 'New'
                    && $decoded['profile']['city'] === 'London';
            })
        );
        $store = new LastKnownIdentityStore($session, $this->consent(true));
        $store->persistFromData([
            'billing_email' => 'new@example.com',
            'customer_profile' => ['first_name' => 'New'],
        ], 1);
    }

    public function testGuestPersistDoesNotWriteSnapshot(): void
    {
        $session = $this->createMock(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn(false);
        $session->expects($this->never())->method('setData');
        $store = new LastKnownIdentityStore($session, $this->consent(true));
        $store->persistFromData([
            'billing_email' => 'guest@example.com',
            'customer_profile' => ['first_name' => 'Ada'],
        ], 1);
    }

    private function consent(bool $granted): ConfigInterface
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('hasTrackingConsent')->with(1)->willReturn($granted);
        return $config;
    }
}
