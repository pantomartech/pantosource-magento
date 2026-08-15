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

    public static function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * @param array<string, string> $fields Keys: email, phone, first_name, last_name, city, state, zip, country
     * @return array<string, string>
     */
    public static function hashFields(array $fields): array
    {
        $map = [
            'email' => 'email_sha256',
            'phone' => 'phone_sha256',
            'first_name' => 'first_name_sha256',
            'last_name' => 'last_name_sha256',
            'city' => 'city_sha256',
            'state' => 'state_sha256',
            'zip' => 'zip_sha256',
            'country' => 'country_sha256',
        ];
        $out = [];
        foreach ($map as $from => $to) {
            if (!array_key_exists($from, $fields)) {
                continue;
            }
            $raw = (string) $fields[$from];
            if ($from === 'phone') {
                $raw = self::normalizePhone($raw);
            }
            $hashed = self::hash($raw);
            if ($hashed !== '') {
                $out[$to] = $hashed;
            }
        }
        return $out;
    }
}
