<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * One section of a tax table: a set of income tax brackets ("bands") or a list of rates and thresholds ("values").
 *
 * Band rows: `from` and `to` are amounts in the table's currency (`to` null = no upper limit) and `rate` is a decimal
 * (0.2 = 20%). Value rows: `unit` says how to read `value` - `rate` (decimal), `money`, `number` or `text`.
 *
 * @phpstan-type BandRow array{from: int|float, to: int|float|null, rate: int|float, label?: string}
 * @phpstan-type ValueRow array{label: string, value: int|float|string|null, unit: string, note?: string, source?: array<string, mixed>, status?: string}
 */
final class Section
{
    /**
     * @param 'bands'|'values'|string $kind
     * @param list<array<string, mixed>> $rows Band rows (BandRow) or value rows (ValueRow), as in the API.
     */
    public function __construct(
        /** Stable identifier, e.g. `federal-income-tax-single`, `usc`, `income-tax`. */
        public readonly string $id,
        public readonly string $title,
        /** `income-tax`, `employee` (employee social security), `employer` or `other`. */
        public readonly string $group,
        public readonly string $kind,
        public readonly array $rows,
        public readonly Source $source,
        /** False while the section is under review. */
        public readonly bool $verified,
        /** What the bands apply to, e.g. "Taxable income, per year". */
        public readonly ?string $basis = null,
        public readonly ?string $note = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data A section in the API shape.
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = is_array($data['rows'] ?? null) ? array_values($data['rows']) : [];
        /** @var array<string, mixed> $source */
        $source = is_array($data['source'] ?? null) ? $data['source'] : [];

        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['title'] ?? ''),
            (string) ($data['group'] ?? ''),
            (string) ($data['kind'] ?? ''),
            $rows,
            Source::fromArray($source),
            (bool) ($data['verified'] ?? false),
            isset($data['basis']) ? (string) $data['basis'] : null,
            isset($data['note']) ? (string) $data['note'] : null,
        );
    }

    public function isBands(): bool
    {
        return $this->kind === 'bands';
    }

    /**
     * Tax on an amount under these bands. See Tables::taxFromBands() for what it does and does not include.
     */
    public function taxOn(int|float $income): float
    {
        return Tables::taxFromBands($this, $income);
    }

    /**
     * The value row with this label (exact match), or null.
     *
     * @return array<string, mixed>|null
     */
    public function value(string $label): ?array
    {
        foreach ($this->rows as $row) {
            if (($row['label'] ?? null) === $label) {
                return $row;
            }
        }

        return null;
    }
}
