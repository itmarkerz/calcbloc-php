<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * One country of the bundled data, as listed in data/manifest.json.
 */
final class CountryInfo
{
    /**
     * @param list<string> $years Tax years bundled, current first.
     * @param list<Region> $regions Regions with their own table (empty for most countries).
     * @param list<string> $files Bundled files, relative to the data directory.
     * @param list<string> $sourceUrls Every official source cited by the country's tables.
     */
    public function __construct(
        /** Country code as used by the API and Tables::load(), e.g. `us`, `new-zealand`. */
        public readonly string $country,
        public readonly string $name,
        public readonly string $currency,
        public readonly string $currentYear,
        public readonly array $years,
        public readonly array $regions,
        public readonly array $files,
        /** The country's tax-table page on calcbloc.com. */
        public readonly string $pageUrl,
        /** The same table from the API. */
        public readonly string $apiUrl,
        /** Date (YYYY-MM-DD) the country's figures were last checked against the official sources. */
        public readonly string $lastVerified,
        public readonly array $sourceUrls,
        public readonly int $rowCount,
        public readonly int $underReviewRows,
    ) {
    }

    /**
     * @param array<string, mixed> $data A manifest entry.
     */
    public static function fromArray(array $data): self
    {
        $regions = [];
        foreach ((array) ($data['regions'] ?? []) as $region) {
            if (is_array($region)) {
                /** @var array<string, mixed> $region */
                $regions[] = Region::fromArray($region);
            }
        }
        $strings = static fn (mixed $list): array => array_values(array_map('strval', (array) $list));
        $currentYear = (string) ($data['current_year'] ?? '');

        return new self(
            (string) ($data['country'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['currency'] ?? ''),
            $currentYear,
            $strings($data['years'] ?? [$currentYear]),
            $regions,
            $strings($data['files'] ?? []),
            (string) ($data['pageUrl'] ?? ''),
            (string) ($data['apiUrl'] ?? ''),
            (string) ($data['lastVerified'] ?? ''),
            $strings($data['sourceUrls'] ?? []),
            (int) ($data['rowCount'] ?? 0),
            (int) ($data['underReviewRows'] ?? 0),
        );
    }

    /**
     * The region with this code or slug (case-insensitive), or null.
     */
    public function region(string $codeOrSlug): ?Region
    {
        $needle = strtolower(trim($codeOrSlug));
        foreach ($this->regions as $region) {
            if (strtolower($region->code) === $needle || strtolower($region->slug) === $needle) {
                return $region;
            }
        }

        return null;
    }
}
