<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * SHA-256 hashed PII fields for order enrichment (Magento-free; PHPUnit-safe).
 */
class HashedPiiHasher
{
    public static function hash(string $value): string
    {
        $trimmed = strtolower(trim($value));
        return $trimmed === '' ? '' : hash('sha256', $trimmed);
    }

    /**
     * Email and phone in every ad-platform form (PiiHashes, current/pii-normalisation.md): phone digits with
     * the country code (national numbers read in $phoneCountries, shipping first) plus the E.164 form, and
     * Google's / Microsoft's email forms when they differ.
     *
     * @param array<string, string> $fields Keys: email, phone, first_name, last_name, city, state, zip, country
     * @param list<string> $phoneCountries ISO country codes, shipping then billing
     * @return array<string, string>
     */
    public static function hashFields(array $fields, array $phoneCountries = []): array
    {
        $out = [];
        if (array_key_exists('email', $fields)) {
            $out += PiiHashes::emailHashes((string) $fields['email']);
        }
        if (array_key_exists('phone', $fields)) {
            $out += PiiHashes::phoneHashes((string) $fields['phone'], $phoneCountries);
        }
        $map = [
            'first_name' => 'first_name_sha256',
            'last_name' => 'last_name_sha256',
            'city' => 'city_sha256',
            'state' => 'state_sha256',
            'zip' => 'zip_sha256',
            'country' => 'country_sha256',
        ];
        foreach ($map as $from => $to) {
            if (!array_key_exists($from, $fields)) {
                continue;
            }
            $hashed = self::hash((string) $fields[$from]);
            if ($hashed !== '') {
                $out[$to] = $hashed;
            }
        }
        return $out;
    }

    /**
     * Hash Advanced Matching fields from a canonical customer_profile (billing first, shipping fills).
     *
     * @param array<string, mixed> $profile
     * @return array<string, string>
     */
    public static function fromProfile(array $profile, string $email = ''): array
    {
        $billing = isset($profile['billing_address']) && is_array($profile['billing_address'])
            ? $profile['billing_address']
            : [];
        $shipping = isset($profile['shipping_address']) && is_array($profile['shipping_address'])
            ? $profile['shipping_address']
            : [];
        $pick = static function (array $keys) use ($profile, $billing, $shipping): string {
            foreach ($keys as $key) {
                foreach ([$profile, $billing, $shipping] as $src) {
                    if (!empty($src[$key]) && is_string($src[$key])) {
                        return (string) $src[$key];
                    }
                }
            }
            return '';
        };
        $countryOf = static function (array $address): string {
            foreach (['country_code', 'country'] as $key) {
                if (!empty($address[$key]) && is_string($address[$key])) {
                    return $address[$key];
                }
            }
            return '';
        };
        return self::hashFields([
            'email' => $email,
            'phone' => $pick(['phone']),
            'first_name' => $pick(['first_name']),
            'last_name' => $pick(['last_name']),
            'city' => $pick(['city']),
            'state' => $pick(['region_code', 'region', 'state']),
            'zip' => $pick(['postal_code', 'zip']),
            'country' => $pick(['country_code', 'country']),
        ], array_values(array_filter([$countryOf($shipping), $countryOf($billing)])));
    }
}
