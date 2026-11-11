<?php

declare(strict_types=1);

namespace CalcBloc;

use CalcBloc\Internal\Json;

/**
 * Offline access to the CalcBloc tax tables bundled with this package (data/): income tax brackets, social security
 * rates and payroll thresholds, each figure with its official source. No network, no API key.
 *
 * The bundled data is the same as the API's /api/v1/tax-tables responses at the time of the package release. For
 * figures changed since then, use Client::taxTables() or update the package.
 *
 * Data licence: CC BY 4.0. Credit "Data by CalcBloc (calcbloc.com/data)" when you publish figures taken from it.
 */
final class Tables
{
    private static ?string $directory = null;

    /** @var array<string, mixed>|null */
    private static ?array $manifest = null;

    /** @var array<string, TaxTable> */
    private static array $cache = [];

    /**
     * Loads one table.
     *
     * @param string $country Country code, e.g. `us`, `uk`, `new-zealand` (see listCountries()).
     * @param string|null $year Tax year, e.g. `2026` or `2026-27`; null or `latest` for the country's current year.
     * @param string|null $region Region code or slug, e.g. `ca` or `california`, `on`, `scotland`; null for the national table.
     *
     * @throws CalcBlocException `unsupported_country`, `unsupported_year` or `unsupported_region` (same codes as the API).
     */
    public static function load(string $country, ?string $year = null, ?string $region = null): TaxTable
    {
        $info = self::country($country);
        if ($info === null) {
            throw new CalcBlocException(sprintf(
                'No bundled tax tables for "%s". Countries: %s.',
                $country,
                implode(', ', array_map(static fn (CountryInfo $c): string => $c->country, self::listCountries())),
            ), 'unsupported_country');
        }
        $resolvedYear = $year === null || strtolower($year) === 'latest' ? $info->currentYear : $year;
        if (!in_array($resolvedYear, $info->years, true)) {
            throw new CalcBlocException(sprintf(
                'No bundled %s tax tables for "%s". Years: %s (or latest).',
                $info->name,
                $year,
                implode(', ', $info->years),
            ), 'unsupported_year');
        }
        $file = $info->country . '/' . $resolvedYear . '.json';
        if ($region !== null && trim($region) !== '') {
            if ($info->regions === []) {
                throw new CalcBlocException(sprintf('%s tax tables have no regional variants.', $info->name), 'unsupported_region');
            }
            $found = $info->region($region);
            if ($found === null) {
                throw new CalcBlocException(sprintf(
                    'Unknown %s region "%s". Regions: %s.',
                    $info->name,
                    $region,
                    implode(', ', array_map(static fn (Region $r): string => $r->code, $info->regions)),
                ), 'unsupported_region');
            }
            $file = $info->country . '/' . $resolvedYear . '/' . $found->code . '.json';
        }
        if (!in_array($file, $info->files, true)) {
            throw new CalcBlocException(sprintf('%s is not bundled with this package; fetch it with Client::taxTables().', $file), 'not_bundled');
        }

        return self::$cache[$file] ??= TaxTable::fromArray(self::read($file));
    }

    /**
     * Every bundled country, in manifest order.
     *
     * @return list<CountryInfo>
     */
    public static function listCountries(): array
    {
        $countries = [];
        foreach ((array) (self::manifest()['countries'] ?? []) as $entry) {
            if (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $countries[] = CountryInfo::fromArray($entry);
            }
        }

        return $countries;
    }

    /**
     * One bundled country by code (case-insensitive), or null.
     */
    public static function country(string $country): ?CountryInfo
    {
        $code = strtolower(trim($country));
        foreach (self::listCountries() as $info) {
            if ($info->country === $code) {
                return $info;
            }
        }

        return null;
    }

    /**
     * The raw data/manifest.json: generatedAt, source, licence, credit, api, countries (with years, regions, files,
     * pageUrl, apiUrl, lastVerified, sourceUrls) and pending (countries not yet included).
     *
     * @return array<string, mixed>
     */
    public static function manifest(): array
    {
        if (self::$manifest === null) {
            /** @var array<string, mixed> $manifest */
            $manifest = self::read('manifest.json');
            self::$manifest = $manifest;
        }

        return self::$manifest;
    }

    /**
     * Tax on an amount under a set of marginal bands: each slice of income between a band's `from` and `to` is taxed
     * at that band's rate, and the slices are added up.
     *
     * Pass a bands section (e.g. `$table->section('federal-income-tax-single')`) or its array form. The amount must
     * be in the section's `basis`, usually taxable income per year after allowances and deductions; this function
     * applies no allowances, credits, reliefs, surcharges, rounding rules or other conditions, so the result is the
     * tax from the bands only. It is a convenience for working with the published brackets, not tax advice.
     *
     * @param Section|array<string, mixed> $section A section of kind `bands`.
     *
     * @throws \InvalidArgumentException If the section is not a bands section.
     */
    public static function taxFromBands(Section|array $section, int|float $income): float
    {
        if (is_array($section)) {
            $section = Section::fromArray($section);
        }
        if (!$section->isBands()) {
            throw new \InvalidArgumentException(sprintf('Section "%s" is of kind "%s"; taxFromBands() needs a "bands" section.', $section->id, $section->kind));
        }
        if (!is_finite((float) $income)) {
            throw new \InvalidArgumentException('Income must be a finite number.');
        }
        $tax = 0.0;
        foreach ($section->rows as $row) {
            $from = $row['from'] ?? null;
            $to = $row['to'] ?? null;
            $rate = $row['rate'] ?? null;
            $isNumber = static fn (mixed $v): bool => is_int($v) || is_float($v);
            if (!$isNumber($from) || !$isNumber($rate) || ($to !== null && !$isNumber($to))) {
                throw new \InvalidArgumentException(sprintf('Section "%s" has a band without numeric from/to/rate.', $section->id));
            }
            $top = $to === null ? (float) $income : min((float) $income, (float) $to);
            if ($top > $from) {
                $tax += ($top - $from) * $rate;
            }
        }

        return $tax;
    }

    /**
     * The directory the bundled data is read from (default: the package's data/ directory).
     */
    public static function directory(): string
    {
        return self::$directory ?? dirname(__DIR__) . '/data';
    }

    /**
     * Reads tables from another directory with the same layout (for example a newer copy of the calcbloc-data
     * tables). Pass null to go back to the bundled data.
     */
    public static function useDirectory(?string $directory): void
    {
        if ($directory !== null && !is_file(rtrim($directory, '/\\') . '/manifest.json')) {
            throw new CalcBlocException(sprintf('No manifest.json in %s.', $directory), 'file_not_readable');
        }
        self::$directory = $directory !== null ? rtrim($directory, '/\\') : null;
        self::$manifest = null;
        self::$cache = [];
    }

    /**
     * @return array<mixed>
     */
    private static function read(string $relative): array
    {
        return Json::decodeFile(self::directory() . '/' . $relative);
    }
}
