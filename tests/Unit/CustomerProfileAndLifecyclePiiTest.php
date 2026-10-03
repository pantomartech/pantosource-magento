<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Model\CheckoutIdentityRepository;
use Panto\Source\Model\CustomerProfileBuilder;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\HashedPiiEnricher;
use Panto\Source\Model\OrderLineSerializer;
use Panto\Source\Observer\OrderCancelObserver;
use Panto\Source\Observer\PaymentInfoObserver;
use Panto\Source\Observer\RefundObserver;
use Magento\Checkout\Model\Session as CheckoutSession;

final class CustomerProfileAndLifecyclePiiTest extends TestCase
{
    public function testProfileFromOrderUsesShippingAndCustomerNames(): void
    {
        $shipping = $this->address([
            'firstname' => 'Ship',
            'lastname' => 'To',
            'telephone' => '3055550100',
            'city' => 'Miami',
            'region' => 'Florida',
            'regionCode' => 'FL',
            'postcode' => '33101',
            'countryId' => 'US',
        ]);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn($shipping);
        $order->method('getCustomerFirstname')->willReturn('Cust');
        $order->method('getCustomerLastname')->willReturn('Name');
        $builder = new CustomerProfileBuilder(new CountryFactory());
        $profile = $builder->profileFromOrder($order);
        $this->assertSame('Ship', $profile['first_name']);
        $this->assertSame('To', $profile['last_name']);
        $this->assertSame('3055550100', $profile['phone']);
        $this->assertSame('FL', $profile['shipping_address']['region_code']);
    }

    public function testProfileFromAddressObjectsOnQuote(): void
    {
        $billing = $this->address([
            'email' => 'guest@example.com',
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'telephone' => '5551112222',
            'city' => 'Boston',
            'regionCode' => 'MA',
            'postcode' => '02108',
            'countryId' => 'US',
        ]);
        $builder = new CustomerProfileBuilder(new CountryFactory());
        $profile = $builder->profileFromAddressObjects($billing, null);
        $this->assertSame('Ada', $profile['first_name']);
        $this->assertSame('Boston', $profile['billing_address']['city']);
    }

    public function testPaymentInfoAttachesQuoteProfile(): void
    {
        $billing = $this->address([
            'email' => 'guest@example.com',
            'firstname' => 'Ada',
            'city' => 'Boston',
            'countryId' => 'US',
        ]);
        $quote = new class ($billing) {
            public function __construct(private mixed $billing)
            {
            }

            public function getBillingAddress(): mixed
            {
                return $this->billing;
            }

            public function getShippingAddress(): mixed
            {
                return null;
            }

            public function getCustomerEmail(): string
            {
                return '';
            }
        };
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getData')->willReturn(null);
        $session->method('getQuote')->willReturn($quote);
        $session->expects($this->exactly(2))->method('setData');
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatched = [];
        $dispatcher->expects($this->exactly(2))->method('dispatch')
            ->willReturnCallback(function (string $name, array $data) use (&$dispatched): bool {
                $dispatched[] = $name;
                return ($data['billing_email'] ?? '') === 'guest@example.com';
            });
        $payment = new class {
            public function getMethod(): string
            {
                return 'checkmo';
            }
        };
        $event = new class ($payment) {
            public function __construct(private mixed $payment)
            {
            }

            public function getPayment(): mixed
            {
                return $this->payment;
            }
        };
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestUri')->willReturn('/checkout/');
        $request->method('getServer')->willReturn('');
        $sut = new PaymentInfoObserver(
            $dispatcher,
            $request,
            $session,
            new CustomerProfileBuilder(new CountryFactory())
        );
        $sut->execute($observer);
        $this->assertSame(['magento_customer_identified', 'add_payment_info'], $dispatched);
    }

    public function testCancelAttachesOrderUser(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStatus')->willReturn('canceled');
        $order->method('getData')->willReturn(null);
        $order->method('setData')->willReturnSelf();
        $order->method('getId')->willReturn(9);
        $order->method('getGrandTotal')->willReturn(10);
        $order->method('getOrderCurrencyCode')->willReturn('USD');
        $order->method('getCustomerId')->willReturn(3);
        $order->method('getCustomerEmail')->willReturn('ada@example.com');
        $order->method('getStoreId')->willReturn(1);
        $order->method('getBillingAddress')->willReturn($this->address(['firstname' => 'Ada', 'countryId' => 'US']));
        $order->method('getShippingAddress')->willReturn(null);
        $history = new class ($order) {
            public function __construct(private mixed $order)
            {
            }

            public function getOrder(): mixed
            {
                return $this->order;
            }
        };
        $event = new class ($history) {
            public function __construct(private mixed $history)
            {
            }

            public function getStatusHistory(): mixed
            {
                return $this->history;
            }
        };
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(
            'magento_order_cancelled',
            $this->callback(static function (array $data): bool {
                return ($data['billing_email'] ?? '') === 'ada@example.com'
                    && ($data['customer_profile']['first_name'] ?? '') === 'Ada';
            })
        );
        $lines = $this->createMock(OrderLineSerializer::class);
        $lines->method('getItems')->willReturn([]);
        $config = $this->createMock(ConfigInterface::class);
        $config->method('isHashedPiiEnabled')->willReturn(false);
        $config->method('hasTrackingConsent')->willReturn(true);
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestUri')->willReturn('/');
        $request->method('getServer')->willReturn('');
        $sut = new OrderCancelObserver(
            $dispatcher,
            $request,
            $lines,
            new CustomerProfileBuilder(new CountryFactory()),
            new HashedPiiEnricher($config),
            $this->onlineCheckoutIdentity()
        );
        $sut->execute($observer);
    }

    public function testRefundAttachesOrderUser(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getId')->willReturn(9);
        $order->method('getOrderCurrencyCode')->willReturn('USD');
        $order->method('getCustomerId')->willReturn(3);
        $order->method('getCustomerEmail')->willReturn('ada@example.com');
        $order->method('getStoreId')->willReturn(1);
        $order->method('getBillingAddress')->willReturn($this->address(['firstname' => 'Ada', 'countryId' => 'US']));
        $order->method('getShippingAddress')->willReturn(null);
        $creditmemo = new class ($order) {
            public function __construct(private mixed $order)
            {
            }

            public function getOrder(): mixed
            {
                return $this->order;
            }

            public function getId(): int
            {
                return 4;
            }

            public function getGrandTotal(): float
            {
                return 5.0;
            }

            public function getAllItems(): array
            {
                return [];
            }
        };
        $event = new class ($creditmemo) {
            public function __construct(private mixed $creditmemo)
            {
            }

            public function getCreditmemo(): mixed
            {
                return $this->creditmemo;
            }
        };
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(
            'magento_refund_created',
            $this->callback(static function (array $data): bool {
                return ($data['billing_email'] ?? '') === 'ada@example.com'
                    && ($data['customer_profile']['first_name'] ?? '') === 'Ada';
            })
        );
        $config = $this->createMock(ConfigInterface::class);
        $config->method('isHashedPiiEnabled')->willReturn(false);
        $config->method('hasTrackingConsent')->willReturn(true);
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRequestUri')->willReturn('/');
        $request->method('getServer')->willReturn('');
        $sut = new RefundObserver(
            $dispatcher,
            $request,
            new CustomerProfileBuilder(new CountryFactory()),
            new HashedPiiEnricher($config),
            $this->onlineCheckoutIdentity()
        );
        $sut->execute($observer);
    }

    private function onlineCheckoutIdentity(): CheckoutIdentityRepository
    {
        $repo = $this->createMock(CheckoutIdentityRepository::class);
        $repo->method('isOnlineStoreOrder')->willReturn(true);
        $repo->method('getSalesChannel')->willReturn('frontend');

        return $repo;
    }

    /**
     * @param array<string, string> $fields
     */
    private function address(array $fields): object
    {
        return new class ($fields) {
            public function __construct(private array $fields)
            {
            }

            public function getStreet(): array
            {
                return [];
            }

            public function getFirstname(): string
            {
                return $this->fields['firstname'] ?? '';
            }

            public function getLastname(): string
            {
                return $this->fields['lastname'] ?? '';
            }

            public function getTelephone(): string
            {
                return $this->fields['telephone'] ?? '';
            }

            public function getCity(): string
            {
                return $this->fields['city'] ?? '';
            }

            public function getRegion(): string
            {
                return $this->fields['region'] ?? '';
            }

            public function getRegionCode(): string
            {
                return $this->fields['regionCode'] ?? '';
            }

            public function getPostcode(): string
            {
                return $this->fields['postcode'] ?? '';
            }

            public function getCountryId(): string
            {
                return $this->fields['countryId'] ?? '';
            }

            public function getCompany(): string
            {
                return '';
            }

            public function getEmail(): string
            {
                return $this->fields['email'] ?? '';
            }
        };
    }
}
