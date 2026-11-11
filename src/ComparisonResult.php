<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * Response of GET /api/v1/compare: the same salary in two countries, converted at the European Central Bank
 * reference rate, with each country's take-home engine.
 */
final class ComparisonResult
{
    /**
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $a Side a: country, place, currency, gross, incomeTax, contributions, retained, net,
     *        effectiveRate, grossInOtherCurrency, netInOtherCurrency, parts, assumptions.
     * @param array<string, mixed> $b Side b, same keys.
     * @param array<string, mixed> $exchangeRate
     * @param array<string, mixed> $difference inACurrency, inBCurrency, percentOfA, deductionRateGapPoints.
     * @param list<string> $warnings
     * @param array<string, mixed> $attribution
     * @param array<string, mixed> $data The full response body.
     */
    public function __construct(
        public readonly array $inputs,
        public readonly array $a,
        public readonly array $b,
        public readonly array $exchangeRate,
        public readonly array $difference,
        public readonly string $interpretation,
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
        /** @var array<string, mixed> $inputs */
        $inputs = (array) ($data['inputs'] ?? []);
        /** @var array<string, mixed> $a */
        $a = (array) ($data['a'] ?? []);
        /** @var array<string, mixed> $b */
        $b = (array) ($data['b'] ?? []);
        /** @var array<string, mixed> $exchangeRate */
        $exchangeRate = (array) ($data['exchangeRate'] ?? []);
        /** @var array<string, mixed> $difference */
        $difference = (array) ($data['difference'] ?? []);
        /** @var array<string, mixed> $attribution */
        $attribution = (array) ($data['attribution'] ?? []);

        return new self(
            $inputs,
            $a,
            $b,
            $exchangeRate,
            $difference,
            (string) ($data['interpretation'] ?? ''),
            (string) ($data['calculatorUrl'] ?? ''),
            array_values(array_map('strval', (array) ($data['warnings'] ?? []))),
            $attribution,
            $data,
        );
    }
}
