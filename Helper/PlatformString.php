<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Normalize Sec-CH-UA-Platform values (Chrome sends quoted tokens like `"Windows"`).
 */
class PlatformString
{
    public static function normalize(string $raw): string
    {
        $trimmed = trim($raw);
        if (strlen($trimmed) >= 2 && $trimmed[0] === '"' && str_ends_with($trimmed, '"')) {
            return trim(substr($trimmed, 1, -1));
        }
        return $trimmed;
    }
}
