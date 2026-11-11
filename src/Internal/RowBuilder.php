<?php

declare(strict_types=1);

namespace CalcBloc\Internal;

/**
 * Rebuilds the flat `rows` array of a tax table from its `sections`, in the order and with the keys the API uses
 * (OpenAPI 1.1.0 and later).
 *
 * The bundled data files leave `rows` out when this class reproduces it exactly (it repeats data already in
 * `sections`), which keeps the package small. bin/sync-tables.php checks the round trip for every file and keeps the
 * original rows whenever the rebuild would differ.
 *
 * @internal
 */
final class RowBuilder
{
    /**
     * @param array<string, mixed> $table A tax table in the API shape (`rows` may be missing).
     * @return list<array<string, mixed>>
     */
    public static function build(array $table): array
    {
        $out = [];
        /** @var list<array<string, mixed>> $sections */
        $sections = $table['sections'] ?? [];
        foreach ($sections as $section) {
            $sectionStatus = !empty($section['verified']) ? 'verified' : 'under_review';
            /** @var array<string, mixed> $sectionSource */
            $sectionSource = $section['source'] ?? [];
            /** @var list<array<string, mixed>> $rows */
            $rows = $section['rows'] ?? [];
            foreach ($rows as $row) {
                if (($section['kind'] ?? null) === 'bands') {
                    $out[] = self::common($table, $section, $sectionSource, null, $sectionStatus) + [
                        'label' => $row['label'] ?? '',
                        'from' => $row['from'] ?? null,
                        'to' => $row['to'] ?? null,
                        'rate' => $row['rate'] ?? null,
                        'value' => null,
                        'unit' => 'rate',
                    ];
                    continue;
                }
                $status = ($row['status'] ?? null) === 'not_applicable' ? 'not_applicable' : $sectionStatus;
                /** @var array<string, mixed> $source */
                $source = isset($row['source']) && is_array($row['source']) ? $row['source'] : $sectionSource;
                $isRate = ($row['unit'] ?? null) === 'rate';
                $out[] = self::common($table, $section, $source, $row['note'] ?? null, $status) + [
                    'label' => $row['label'] ?? null,
                    'from' => null,
                    'to' => null,
                    'rate' => $isRate ? ($row['value'] ?? null) : null,
                    'value' => $isRate ? null : ($row['value'] ?? null),
                    'unit' => $row['unit'] ?? null,
                ];
            }
        }

        return $out;
    }

    /**
     * Returns the table with `rows` placed where the API places it: after `years_available`, before `pageUrl`.
     *
     * @param array<string, mixed> $stripped
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function withRows(array $stripped, array $rows): array
    {
        $out = [];
        $placed = false;
        foreach ($stripped as $key => $value) {
            if ($key === 'rows') {
                continue;
            }
            if ($key === 'pageUrl' && !$placed) {
                $out['rows'] = $rows;
                $placed = true;
            }
            $out[$key] = $value;
        }
        if (!$placed) {
            $out['rows'] = $rows;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $table
     * @param array<string, mixed> $section
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function common(array $table, array $section, array $source, mixed $note, string $status): array
    {
        return [
            'section_id' => $section['id'] ?? null,
            'section' => $section['title'] ?? null,
            'group' => $section['group'] ?? null,
            'basis' => $section['basis'] ?? '',
            'note' => $note ?? $section['note'] ?? '',
            'tax_year' => $table['year'] ?? null,
            'effective_from' => $source['effectiveFrom'] ?? $table['effectiveFrom'] ?? null,
            'effective_to' => array_key_exists('effectiveTo', $source) ? $source['effectiveTo'] : ($table['effectiveTo'] ?? null),
            'source_title' => $source['title'] ?? null,
            'source_url' => $source['url'] ?? null,
            'retrieved_at' => $source['retrievedAt'] ?? null,
            'status' => $status,
        ];
    }
}
