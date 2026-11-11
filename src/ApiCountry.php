<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * One entry of GET /api/v1/countries: what the API supports for a country.
 */
final class ApiCountry
{
    /**
     * @param list<string> $years Tax years served as tax tables, current first.
     * @param list<string> $availableEndpoints e.g. `net-salary`, `employer-cost`, `compare`, `tax-tables`.
     * @param array<string, array<string, mixed>> $endpoints Per endpoint: path, calculatorUrl or pageUrl, and the
     *        accepted parameters (name, type, required, values, min, max, default, unit, description).
     * @param array<string, mixed> $data The entry as returned by the API.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $currency,
        public readonly string $currentYear,
        public readonly array $years,
        public readonly array $availableEndpoints,
        /** True when the country has tax tables only (calculation endpoints answer 400 unsupported_country). */
        public readonly bool $tablesOnly,
        public readonly array $endpoints,
        public readonly array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $strings = static fn (mixed $list): array => array_values(array_map('strval', (array) $list));
        /** @var array<string, array<string, mixed>> $endpoints */
        $endpoints = is_array($data['endpoints'] ?? null) ? $data['endpoints'] : [];

        return new self(
            (string) ($data['code'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['currency'] ?? ''),
            (string) ($data['current_year'] ?? ''),
            $strings($data['years_available'] ?? $data['years'] ?? []),
            $strings($data['available_endpoints'] ?? []),
            (bool) ($data['tables_only'] ?? false),
            $endpoints,
            $data,
        );
    }

    public function supports(string $endpoint): bool
    {
        return in_array($endpoint, $this->availableEndpoints, true);
    }
}
