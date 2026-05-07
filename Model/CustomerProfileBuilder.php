<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Customer\Model\Address as CustomerAddress;
use Magento\Customer\Model\Customer;
use Magento\Directory\Model\CountryFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Builds canonical user.profile (postal_address + person fields) for Magento orders and customers.
 */
class CustomerProfileBuilder
{
    public function __construct(
        private readonly CountryFactory $countryFactory
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function profileFromOrder(OrderInterface $order): array
    {
        $billing = $this->postalFromOrderAddress($order->getBillingAddress());
        $shipping = $this->postalFromOrderAddress($order->getShippingAddress());
        $ba = $order->getBillingAddress();
        $fn = $ba ? trim((string) $ba->getFirstname()) : '';
        $ln = $ba ? trim((string) $ba->getLastname()) : '';
        $profile = array_filter(
            [
                'first_name' => $fn,
                'last_name' => $ln,
                'full_name' => trim($fn . ' ' . $ln),
                'phone' => $ba ? trim((string) $ba->getTelephone()) : '',
                'company' => $ba ? trim((string) $ba->getCompany()) : '',
                'billing_address' => $billing,
                'shipping_address' => $shipping,
                'addresses_match' => $this->orderAddressesMatch($order),
            ],
            static function (mixed $v): bool {
                if (is_array($v)) {
                    return $v !== [];
                }
                if (is_bool($v)) {
                    return true;
                }
                return is_string($v) && $v !== '';
            }
        );
        if (isset($profile['full_name']) && $profile['full_name'] === '') {
            unset($profile['full_name']);
        }
        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    public function profileFromCustomerModel(Customer $customer): array
    {
        if (!(int) $customer->getId()) {
            return [];
        }
        $fn = trim((string) $customer->getFirstname());
        $ln = trim((string) $customer->getLastname());
        $billing = [];
        $shipping = [];
        $defBill = $customer->getDefaultBilling();
        if ($defBill) {
            $a = $customer->getAddressById($defBill);
            if ($a instanceof CustomerAddress) {
                $billing = $this->postalFromCustomerAddress($a);
            }
        }
        $defShip = $customer->getDefaultShipping();
        if ($defShip) {
            $a = $customer->getAddressById($defShip);
            if ($a instanceof CustomerAddress) {
                $shipping = $this->postalFromCustomerAddress($a);
            }
        }
        /** @var array<string, mixed> $profile */
        $profile = [];
        if ($fn !== '') {
            $profile['first_name'] = $fn;
        }
        if ($ln !== '') {
            $profile['last_name'] = $ln;
        }
        $full = trim($fn . ' ' . $ln);
        if ($full !== '') {
            $profile['full_name'] = $full;
        }
        if ($billing !== []) {
            $profile['billing_address'] = $billing;
        }
        if ($shipping !== []) {
            $profile['shipping_address'] = $shipping;
        }
        if ($defBill) {
            $a = $customer->getAddressById($defBill);
            if ($a instanceof CustomerAddress) {
                $t = trim((string) $a->getTelephone());
                if ($t !== '') {
                    $profile['phone'] = $t;
                }
                $c = trim((string) $a->getCompany());
                if ($c !== '') {
                    $profile['company'] = $c;
                }
            }
        }
        return $profile;
    }

    /**
     * @return array<string, string>
     */
    private function postalFromOrderAddress(?OrderAddressInterface $addr): array
    {
        if ($addr === null) {
            return [];
        }
        $street = $addr->getStreet() ?? [];
        $line1 = isset($street[0]) ? trim((string) $street[0]) : '';
        $line2 = isset($street[1]) ? trim((string) $street[1]) : '';
        $cc = trim((string) $addr->getCountryId());
        $countryName = $cc;
        if ($cc !== '') {
            try {
                $country = $this->countryFactory->create()->loadByCode($cc);
                if ($country->getName() !== null && $country->getName() !== '') {
                    $countryName = (string) $country->getName();
                }
            } catch (\Throwable) {
                $countryName = $cc;
            }
        }
        $region = trim((string) $addr->getRegion());
        $regionCode = trim((string) ($addr->getRegionCode() ?: $addr->getRegion()));

        return array_filter(
            [
                'line1' => $line1,
                'line2' => $line2,
                'city' => trim((string) $addr->getCity()),
                'region' => $region !== '' ? $region : $regionCode,
                'region_code' => $regionCode !== '' ? $regionCode : $region,
                'postal_code' => trim((string) $addr->getPostcode()),
                'country' => $countryName,
                'country_code' => $cc,
                'phone' => trim((string) $addr->getTelephone()),
                'first_name' => trim((string) $addr->getFirstname()),
                'last_name' => trim((string) $addr->getLastname()),
                'company' => trim((string) $addr->getCompany()),
            ],
            static fn (string $v): bool => $v !== ''
        );
    }

    /**
     * @return array<string, string>
     */
    private function postalFromCustomerAddress(CustomerAddress $addr): array
    {
        $street = $addr->getStreet();
        $street = is_array($street) ? $street : [(string) $street];
        $line1 = isset($street[0]) ? trim((string) $street[0]) : '';
        $line2 = isset($street[1]) ? trim((string) $street[1]) : '';
        $cc = trim((string) $addr->getCountryId());
        $countryName = $cc;
        if ($cc !== '') {
            try {
                $country = $this->countryFactory->create()->loadByCode($cc);
                if ($country->getName() !== null && $country->getName() !== '') {
                    $countryName = (string) $country->getName();
                }
            } catch (\Throwable) {
                $countryName = $cc;
            }
        }
        $region = trim((string) $addr->getRegion());
        $regionCode = trim((string) ($addr->getRegionCode() ?: $region));

        return array_filter(
            [
                'line1' => $line1,
                'line2' => $line2,
                'city' => trim((string) $addr->getCity()),
                'region' => $region !== '' ? $region : $regionCode,
                'region_code' => $regionCode !== '' ? $regionCode : $region,
                'postal_code' => trim((string) $addr->getPostcode()),
                'country' => $countryName,
                'country_code' => $cc,
                'phone' => trim((string) $addr->getTelephone()),
                'first_name' => trim((string) $addr->getFirstname()),
                'last_name' => trim((string) $addr->getLastname()),
                'company' => trim((string) $addr->getCompany()),
            ],
            static fn (string $v): bool => $v !== ''
        );
    }

    private function orderAddressesMatch(OrderInterface $order): ?bool
    {
        $b = $order->getBillingAddress();
        $s = $order->getShippingAddress();
        if ($b === null || $s === null) {
            return null;
        }
        foreach (['getStreet', 'getCity', 'getPostcode', 'getCountryId'] as $m) {
            if (method_exists($b, $m) && method_exists($s, $m)) {
                $bv = $b->$m();
                $sv = $s->$m();
                if ($m === 'getStreet') {
                    $bs = is_array($bv) ? implode("\n", $bv) : (string) $bv;
                    $ss = is_array($sv) ? implode("\n", $sv) : (string) $sv;
                    if (strtolower(trim($bs)) !== strtolower(trim($ss))) {
                        return false;
                    }
                    continue;
                }
                if (strtolower(trim((string) $bv)) !== strtolower(trim((string) $sv))) {
                    return false;
                }
            }
        }
        return true;
    }
}
