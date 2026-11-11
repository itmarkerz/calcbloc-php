<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * HTTP 429 `rate_limited`: the per-minute or daily request limit of your key (or of anonymous access) is used up.
 *
 * Without an API key the message says the free allowance (50 requests a day, 5 a minute) is used up and links to
 * https://calcbloc.com/developers/keys for a free key (5,000 a day), set with new Client(apiKey: ...) or the
 * CALCBLOC_API_KEY environment variable. With a key it is the API's message for that key's limit.
 *
 * getRetryAfter() is the number of seconds the API asks you to wait (the Retry-After header).
 * Limits are listed at https://calcbloc.com/developers#limits.
 */
final class RateLimitException extends CalcBlocException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $message,
        ?string $docs = null,
        private readonly ?int $retryAfter = null,
        private readonly ?RateLimit $rateLimit = null,
        array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 'rate_limited', 429, $docs, $previous, $headers);
    }

    /** Seconds to wait before retrying (Retry-After), if the API sent it. */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /** The X-RateLimit-* headers of the refused request, if sent. */
    public function getRateLimit(): ?RateLimit
    {
        return $this->rateLimit;
    }
}
