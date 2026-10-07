<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Validate a client-supplied event.occurred_at (UTC ISO-8601).
 * Mirrors Woo / event-ingestion future skew (default 6h); rejects unparseable or ancient values.
 */
class ClientOccurredAt
{
    public const DEFAULT_FUTURE_SKEW_SECONDS = 6 * 3600;
    public const DEFAULT_MAX_PAST_SECONDS = 30 * 86400;

    /**
     * @param mixed $candidate Raw occurred_at from frontend payload data.
     * @return string Empty when invalid; otherwise ISO-8601 UTC with milliseconds and Z.
     */
    public static function normalize(
        mixed $candidate,
        ?int $now = null,
        int $maxFutureSec = self::DEFAULT_FUTURE_SKEW_SECONDS,
        int $maxPastSec = self::DEFAULT_MAX_PAST_SECONDS
    ): string {
        if (!is_string($candidate) || trim($candidate) === '') {
            return '';
        }
        $raw = trim($candidate);
        $ms = strtotime($raw);
        if ($ms === false) {
            return '';
        }
        $nowTs = $now ?? time();
        if ($ms > $nowTs + max(0, $maxFutureSec)) {
            return '';
        }
        if ($ms < $nowTs - max(0, $maxPastSec)) {
            return '';
        }
        return gmdate('Y-m-d\TH:i:s', $ms) . '.000Z';
    }
}
