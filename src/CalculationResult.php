<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * Response of GET /api/v1/net-salary or /api/v1/employer-cost: the output of the same engine and official data as
 * the matching calculator on calcbloc.com.
 */
final class CalculationResult
{
    /**
     * @param array<string, mixed> $inputs The validated inputs used, defaults filled in.
     * @param array<string, int|float|null> $summary net-salary: grossAnnual, netAnnual, totalDeductions, effectiveRate;
     *        employer-cost: salary, totalCost, monthlyCost, multiplier.
     * @param array<string, int|float> $results Every numeric output of the engine, annual unless the key says otherwise.
     * @param array{title?: string, columns?: list<string>, rows?: list<array{label: string, values: list<mixed>}>}|null $breakdown
     * @param list<string> $notes
     * @param list<string> $warnings
     * @param array<string, mixed> $attribution
     * @param array<string, mixed> $data The full response body.
     */
    public function __construct(
        /** `net-salary` or `employer-cost`. */
        public readonly string $endpoint,
        public readonly string $country,
        public readonly string $countryName,
        /** The tax year used (`latest` resolves to the current year). */
        public readonly string $year,
        public readonly string $requestedYear,
        public readonly string $currency,
        public readonly array $inputs,
        public readonly array $summary,
        public readonly array $results,
        public readonly ?array $breakdown,
        public readonly array $notes,
        /** The matching calculator on calcbloc.com. */
        public readonly string $calculatorUrl,
        public readonly array $warnings,
        public readonly array $attribution,
        public readonly array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $strings = static fn (mixed $list): array => array_values(array_map('strval', (array) $list));
        /** @var array<string, mixed> $inputs */
        $inputs = (array) ($data['inputs'] ?? []);
        /** @var array<string, int|float|null> $summary */
        $summary = (array) ($data['summary'] ?? []);
        /** @var array<string, int|float> $results */
        $results = (array) ($data['results'] ?? []);
        /** @var array{title?: string, columns?: list<string>, rows?: list<array{label: string, values: list<mixed>}>}|null $breakdown */
        $breakdown = is_array($data['breakdown'] ?? null) ? $data['breakdown'] : null;
        /** @var array<string, mixed> $attribution */
        $attribution = (array) ($data['attribution'] ?? []);
        $year = (string) ($data['year'] ?? '');

        return new self(
            (string) ($data['endpoint'] ?? ''),
            (string) ($data['country'] ?? ''),
            (string) ($data['countryName'] ?? ''),
            $year,
            (string) ($data['requested_year'] ?? $year),
            (string) ($data['currency'] ?? ''),
            $inputs,
            $summary,
            $results,
            $breakdown,
            $strings($data['notes'] ?? []),
            (string) ($data['calculatorUrl'] ?? ''),
            $strings($data['warnings'] ?? []),
            $attribution,
            $data,
        );
    }
}
