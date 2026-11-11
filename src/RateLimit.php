<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * The X-RateLimit-* headers of an API response: the limit of the window that is closest to running out, the requests
 * left in it, and the seconds until it resets.
 */
final class RateLimit
{
    public function __construct(
        public readonly int $limit,
        public readonly int $remaining,
        public readonly int $resetSeconds,
    ) {
    }

    /**
     * @param array<string, string> $headers Lower-case header names.
     */
    public static function fromHeaders(array $headers): ?self
    {
        foreach (['x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset'] as $name) {
            if (!isset($headers[$name]) || !is_numeric($headers[$name])) {
                return null;
            }
        }

        return new self((int) $headers['x-ratelimit-limit'], (int) $headers['x-ratelimit-remaining'], (int) $headers['x-ratelimit-reset']);
    }
}
