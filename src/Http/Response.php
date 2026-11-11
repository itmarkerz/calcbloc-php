<?php

declare(strict_types=1);

namespace CalcBloc\Http;

/**
 * A received HTTP response.
 */
final class Response
{
    /** @var array<string, string> */
    public readonly array $headers;

    /**
     * @param array<string, string|list<string>> $headers Header names in any case; repeated headers may be lists.
     */
    public function __construct(
        public readonly int $status,
        array $headers,
        public readonly string $body,
    ) {
        $normalised = [];
        foreach ($headers as $name => $value) {
            $normalised[strtolower((string) $name)] = is_array($value) ? implode(', ', $value) : $value;
        }
        $this->headers = $normalised;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
