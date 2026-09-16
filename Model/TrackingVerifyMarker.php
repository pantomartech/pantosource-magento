<?php
declare(strict_types=1);

namespace Panto\Source\Model;

/**
 * Portal install-tracking HTML comment: SHA-256 of the 62-char ingestion API key.
 */
class TrackingVerifyMarker
{
    private const API_KEY_PATTERN = '/^[A-Za-z0-9]{62}$/';

    public function build(string $apiKey): string
    {
        $key = trim($apiKey);
        if ($key === '' || preg_match(self::API_KEY_PATTERN, $key) !== 1) {
            return '';
        }

        return '<!-- code: ' . hash('sha256', $key) . ' -->';
    }
}
