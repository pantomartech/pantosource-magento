<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Checkout identity snapshot shape (order-identity table JSON). Magento-free for PHPUnit.
 */
class CheckoutIdentitySnapshot
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function fromEventData(array $data, string $ip = '', string $ua = ''): array
    {
        $ctx = isset($data['context']) && is_array($data['context']) ? $data['context'] : [];
        $attrib = isset($ctx['attribution']) && is_array($ctx['attribution']) ? $ctx['attribution'] : [];
        $first = isset($attrib['first_touch']) && is_array($attrib['first_touch']) ? $attrib['first_touch'] : [];
        $last = isset($attrib['last_touch']) && is_array($attrib['last_touch']) ? $attrib['last_touch'] : [];

        $snapshot = [
            'first_touch' => $first,
            'last_touch' => $last,
        ];

        $cookies = MatchingSignalFields::cookiesFromSources([
            $data,
            $last,
            $first,
            isset($data['last_cookies']) && is_array($data['last_cookies']) ? $data['last_cookies'] : [],
        ]);
        if ($cookies !== []) {
            $snapshot['last_cookies'] = $cookies;
        }
        if ($ip !== '') {
            $snapshot['last_client_ip'] = $ip;
        }
        if ($ua !== '') {
            $snapshot['last_user_agent'] = $ua;
        }

        return $snapshot;
    }

    /**
     * Merge stored snapshot onto conversion event data (fill-only matching keys).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $decoded
     * @return array<string, mixed>
     */
    public static function applyToEventData(array $data, array $decoded): array
    {
        $hasLive = MatchingSignalFields::hasLiveBrowserCookies($data);
        $ctx = isset($data['context']) && is_array($data['context']) ? $data['context'] : [];
        $attrib = isset($ctx['attribution']) && is_array($ctx['attribution']) ? $ctx['attribution'] : [];
        $decodedFirst = isset($decoded['first_touch']) && is_array($decoded['first_touch']) ? $decoded['first_touch'] : [];
        $decodedLast = isset($decoded['last_touch']) && is_array($decoded['last_touch']) ? $decoded['last_touch'] : [];
        $decodedCookies = isset($decoded['last_cookies']) && is_array($decoded['last_cookies']) ? $decoded['last_cookies'] : [];

        if (empty($attrib['first_touch']) && $decodedFirst !== []) {
            $attrib['first_touch'] = $decodedFirst;
        } elseif ($decodedFirst !== [] && isset($attrib['first_touch']) && is_array($attrib['first_touch'])) {
            $attrib['first_touch'] = MatchingSignalFields::union($attrib['first_touch'], $decodedFirst);
        }
        if (empty($attrib['last_touch']) && $decodedLast !== []) {
            $attrib['last_touch'] = $decodedLast;
        } elseif ($decodedLast !== [] && isset($attrib['last_touch']) && is_array($attrib['last_touch'])) {
            $attrib['last_touch'] = MatchingSignalFields::union($attrib['last_touch'], $decodedLast);
        }
        if ($decodedCookies !== []) {
            if (empty($attrib['last_touch']) || !is_array($attrib['last_touch'])) {
                $attrib['last_touch'] = [];
            }
            $attrib['last_touch'] = MatchingSignalFields::union($attrib['last_touch'], $decodedCookies);
        }

        $ctx['attribution'] = $attrib;
        $data['context'] = $ctx;
        if (empty($data['attribution_snapshot'])) {
            $data['attribution_snapshot'] = $decoded;
        }

        $data = MatchingSignalFields::fillFromAttribution($data);

        if (!$hasLive) {
            $ip = MatchingSignalFields::nonempty($decoded['last_client_ip'] ?? '');
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $data['last_client_ip'] = $ip;
            }
            $ua = MatchingSignalFields::nonempty($decoded['last_user_agent'] ?? '');
            if ($ua !== '') {
                $data['last_user_agent'] = $ua;
                if (empty($ctx['user_agent'])) {
                    $ctx['user_agent'] = $ua;
                    $data['context'] = $ctx;
                }
            }
        }

        return $data;
    }
}
