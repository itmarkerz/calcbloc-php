<?php

declare(strict_types=1);

namespace CalcBloc;

use CalcBloc\Internal\RowBuilder;

/**
 * The official tax tables of one country (or region) for one tax year: income tax brackets, thresholds, employee and
 * employer social security rates, with the official source and check date of every figure.
 *
 * The same object comes back from the bundled data (Tables::load()) and from the API (Client::taxTables()).
 * toArray() returns the data in the exact shape of GET /api/v1/tax-tables/{country}/{year}.
 *
 * @phpstan-type ExportRow array{section_id: string, section: string, group: string, basis: string, note: string, tax_year: string, effective_from: string, effective_to: string|null, source_title: string, source_url: string, retrieved_at: string, status: string, label: string, from: int|float|null, to: int|float|null, rate: int|float|null, value: int|float|string|null, unit: string}
 */
final class TaxTable
{
    /**
     * @param list<Section> $sections
     * @param list<Source> $sources
     * @param list<string> $underReview Ids of sections under review.
     * @param list<string> $yearsAvailable
     * @param list<array<string, mixed>> $rows One flat row per band or value (ExportRow), as in the CSV export.
     * @param array<string, mixed> $attribution
     * @param array<string, mixed> $data The complete table in the API shape.
     */
    private function __construct(
        /** Country code as used by the API, e.g. `us`, `uk`, `new-zealand`. */
        public readonly string $country,
        public readonly string $countryName,
        /** Tax year, e.g. `2026` or `2026-27`. */
        public readonly string $year,
        public readonly string $yearLabel,
        /** False for an earlier year kept for reference. */
        public readonly bool $isCurrent,
        public readonly ?Region $region,
        /** ISO 4217 currency code of every amount in the table. */
        public readonly string $currency,
        public readonly string $effectiveFrom,
        public readonly ?string $effectiveTo,
        /** `verified` or `unverified`. */
        public readonly string $status,
        public readonly array $underReview,
        public readonly bool $dataVerified,
        public readonly array $sections,
        public readonly array $sources,
        public readonly string $currentYear,
        public readonly array $yearsAvailable,
        public readonly array $rows,
        /** The calcbloc.com page that shows this table. */
        public readonly string $pageUrl,
        public readonly array $attribution,
        public readonly ?string $notice,
        private readonly array $data,
    ) {
    }

    /**
     * Builds a table from the API shape. A missing `rows` array is rebuilt from `sections`.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['rows']) || !is_array($data['rows'])) {
            $data = RowBuilder::withRows($data, RowBuilder::build($data));
        }
        $sections = [];
        foreach ((array) ($data['sections'] ?? []) as $section) {
            if (is_array($section)) {
                /** @var array<string, mixed> $section */
                $sections[] = Section::fromArray($section);
            }
        }
        $sources = [];
        foreach ((array) ($data['sources'] ?? []) as $source) {
            if (is_array($source)) {
                /** @var array<string, mixed> $source */
                $sources[] = Source::fromArray($source);
            }
        }
        /** @var array<string, mixed>|null $region */
        $region = is_array($data['region'] ?? null) ? $data['region'] : null;
        $year = (string) ($data['year'] ?? '');
        /** @var list<array<string, mixed>> $rows */
        $rows = array_values((array) $data['rows']);
        /** @var array<string, mixed> $attribution */
        $attribution = is_array($data['attribution'] ?? null) ? $data['attribution'] : [];

        return new self(
            (string) ($data['country'] ?? ''),
            (string) ($data['countryName'] ?? ''),
            $year,
            (string) ($data['yearLabel'] ?? $year),
            (bool) ($data['isCurrent'] ?? true),
            $region !== null ? Region::fromArray($region) : null,
            (string) ($data['currency'] ?? ''),
            (string) ($data['effectiveFrom'] ?? ''),
            isset($data['effectiveTo']) ? (string) $data['effectiveTo'] : null,
            (string) ($data['status'] ?? ''),
            array_values(array_map('strval', (array) ($data['underReview'] ?? []))),
            (bool) ($data['dataVerified'] ?? false),
            $sections,
            $sources,
            (string) ($data['current_year'] ?? $year),
            array_values(array_map('strval', (array) ($data['years_available'] ?? [$year]))),
            $rows,
            (string) ($data['pageUrl'] ?? ''),
            $attribution,
            isset($data['notice']) ? (string) $data['notice'] : null,
            $data,
        );
    }

    /**
     * The section with this id, or null.
     */
    public function section(string $id): ?Section
    {
        foreach ($this->sections as $section) {
            if ($section->id === $id) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Sections of one group: `income-tax`, `employee`, `employer` or `other`.
     *
     * @return list<Section>
     */
    public function sectionsInGroup(string $group): array
    {
        return array_values(array_filter($this->sections, static fn (Section $s): bool => $s->group === $group));
    }

    /**
     * The table in the exact shape of the API response (including the flat `rows`).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
