<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Customer\Model\Address as CustomerAddress;
use Magento\Customer\Model\Customer;
use Magento\Directory\Model\CountryFactory;
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
        $billing = $this->postalFromAddress($order->getBillingAddress());
        $shipping = $this->postalFromAddress($order->getShippingAddress());
        $ba = $order->getBillingAddress();
        $sa = $order->getShippingAddress();
        $fn = $ba ? trim((string) $ba->getFirstname()) : '';
        $ln = $ba ? trim((string) $ba->getLastname()) : '';
        if ($fn === '' && $sa) {
            $fn = trim((string) $sa->getFirstname());
        }
        if ($ln === '' && $sa) {
            $ln = trim((string) $sa->getLastname());
        }
        if ($fn === '' && method_exists($order, 'getCustomerFirstname')) {
            $fn = trim((string) $order->getCustomerFirstname());
        }
        if ($ln === '' && method_exists($order, 'getCustomerLastname')) {
            $ln = trim((string) $order->getCustomerLastname());
        }
        $phone = $ba ? trim((string) $ba->getTelephone()) : '';
        if ($phone === '' && $sa) {
            $phone = trim((string) $sa->getTelephone());
        }
        $profile = array_filter(
            [
                'first_name' => $fn,
                'last_name' => $ln,
                'full_name' => trim($fn . ' ' . $ln),
                'phone' => $phone,
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
        if (!isset($profile['phone']) && $defShip) {
            $a = $customer->getAddressById($defShip);
            if ($a instanceof CustomerAddress) {
                $t = trim((string) $a->getTelephone());
                if ($t !== '') {
                    $profile['phone'] = $t;
                }
            }
        }
        return $profile;
    }

    /**
     * Quote or order address duck-type (getStreet, getCity, getRegionCode, …).
     *
     * @return array<string, mixed>
     */
    public function profileFromAddressObjects(?object $billing, ?object $shipping): array
    {
        $billPostal = $this->postalFromAddress($billing);
        $shipPostal = $this->postalFromAddress($shipping);
        $fn = $this->addressString($billing, 'getFirstname');
        $ln = $this->addressString($billing, 'getLastname');
        if ($fn === '') {
            $fn = $this->addressString($shipping, 'getFirstname');
        }
        if ($ln === '') {
            $ln = $this->addressString($shipping, 'getLastname');
        }
        $phone = $this->addressString($billing, 'getTelephone');
        if ($phone === '') {
            $phone = $this->addressString($shipping, 'getTelephone');
        }
        $profile = array_filter(
            [
                'first_name' => $fn,
                'last_name' => $ln,
                'full_name' => trim($fn . ' ' . $ln),
                'phone' => $phone,
                'company' => $this->addressString($billing, 'getCompany'),
                'billing_address' => $billPostal,
                'shipping_address' => $shipPostal,
            ],
            static function (mixed $v): bool {
                if (is_array($v)) {
                    return $v !== [];
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
     * @return array<string, string>
     */
    private function postalFromAddress(?object $addr): array
    {
        if ($addr === null) {
            return [];
        }
        $street = method_exists($addr, 'getStreet') ? ($addr->getStreet() ?? []) : [];
        $street = is_array($street) ? $street : [(string) $street];
        $line1 = isset($street[0]) ? trim((string) $street[0]) : '';
        $line2 = isset($street[1]) ? trim((string) $street[1]) : '';
        $cc = $this->addressString($addr, 'getCountryId');
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
        $region = $this->addressString($addr, 'getRegion');
        $regionCode = $this->addressString($addr, 'getRegionCode');
        if ($regionCode === '') {
            $regionCode = $region;
        }

        return array_filter(
            [
                'line1' => $line1,
                'line2' => $line2,
                'city' => $this->addressString($addr, 'getCity'),
                'region' => $region !== '' ? $region : $regionCode,
                'region_code' => $regionCode !== '' ? $regionCode : $region,
                'postal_code' => $this->addressString($addr, 'getPostcode'),
                'country' => $countryName,
                'country_code' => $cc,
                'phone' => $this->addressString($addr, 'getTelephone'),
                'first_name' => $this->addressString($addr, 'getFirstname'),
                'last_name' => $this->addressString($addr, 'getLastname'),
                'company' => $this->addressString($addr, 'getCompany'),
            ],
            static fn (string $v): bool => $v !== ''
        );
    }

    private function addressString(?object $addr, string $method): string
    {
        if ($addr === null || !method_exists($addr, $method)) {
            return '';
        }
        return trim((string) $addr->$method());
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
