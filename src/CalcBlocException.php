<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * Any error raised by this package.
 *
 * API errors carry the fields of the API's error body, `{"error": {"code", "message", "docs"}}`:
 *
 * - getErrorCode(): the machine-readable code, e.g. `unsupported_country`, `invalid_parameter`, `invalid_key`,
 *   `api_key_required`, `rate_limited`, `service_unavailable`. Offline lookups use the same codes
 *   (`unsupported_country`, `unsupported_year`, `unsupported_region`).
 * - getStatus() / getCode(): the HTTP status (null / 0 when no HTTP response was involved).
 * - getDocs(): the documentation link the API sent with the error.
 */
class CalcBlocException extends \RuntimeException
{
    /**
     * @param array<string, string> $headers Response headers (lower-case names), when the error came from the API.
     */
    public function __construct(
        string $message,
        private readonly string $errorCode = 'error',
        private readonly ?int $status = null,
        private readonly ?string $docs = null,
        ?\Throwable $previous = null,
        private readonly array $headers = [],
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    /** Machine-readable error code, as sent by the API (`error.code`). */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** HTTP status code, or null when the error did not come from an HTTP response. */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /** Documentation link sent with the error (`error.docs`), if any. */
    public function getDocs(): ?string
    {
        return $this->docs;
    }

    /**
     * Response headers (lower-case names) of the failed request.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
