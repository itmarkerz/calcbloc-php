<?php

declare(strict_types=1);

namespace CalcBloc\Tests;

use CalcBloc\Tables;
use CalcBloc\TaxTable;
use PHPUnit\Framework\TestCase;

/**
 * Sanity checks over every bundled table: each flat row cites an official source and a check date, sections are
 * well formed and band rows are usable by taxFromBands().
 */
final class SchemaTest extends TestCase
{
    private const DATE = '~^\d{4}-\d{2}-\d{2}$~';
    /** Absolute http(s) URL with a dotted host. (One official PDF path contains literal spaces, so the path is not checked.) */
    private const URL = '~^https?://[^\s/]+\.[^\s/]+(/.*)?$~';

    /**
     * @return iterable<string, TaxTable>
     */
    private static function allTables(): iterable
    {
        foreach (Tables::listCountries() as $c) {
            foreach ($c->years as $year) {
                yield "{$c->country}/$year" => Tables::load($c->country, $year);
                foreach ($c->regions as $region) {
                    if (in_array("{$c->country}/$year/{$region->code}.json", $c->files, true)) {
                        yield "{$c->country}/$year/{$region->code}" => Tables::load($c->country, $year, $region->code);
                    }
                }
            }
        }
    }

    public function testEveryRowHasSourceUrlAndRetrievedAt(): void
    {
        $rows = 0;
        foreach (self::allTables() as $name => $table) {
            self::assertNotEmpty($table->rows, "$name has rows");
            foreach ($table->rows as $i => $row) {
                $where = sprintf('%s row %d (%s "%s")', $name, $i, $row['section_id'] ?? '?', $row['label'] ?? '');
                self::assertIsString($row['source_url'] ?? null, "$where: source_url");
                self::assertMatchesRegularExpression(self::URL, $row['source_url'], "$where: source_url");
                self::assertIsString($row['retrieved_at'] ?? null, "$where: retrieved_at");
                self::assertMatchesRegularExpression(self::DATE, $row['retrieved_at'], "$where: retrieved_at");
                self::assertSame($table->year, $row['tax_year'], "$where: tax_year");
                self::assertContains($row['status'], ['verified', 'under_review', 'not_applicable'], "$where: status");
                self::assertContains($row['unit'], ['rate', 'money', 'number', 'text'], "$where: unit");
                $rows++;
            }
        }
        self::assertGreaterThan(0, $rows);
    }

    public function testTableHeaders(): void
    {
        foreach (self::allTables() as $name => $table) {
            self::assertNotSame('', $table->countryName, $name);
            self::assertMatchesRegularExpression('~^[A-Z]{3}$~', $table->currency, $name);
            self::assertMatchesRegularExpression(self::DATE, $table->effectiveFrom, $name);
            self::assertStringStartsWith('https://calcbloc.com/', $table->pageUrl, $name);
            self::assertContains($table->year, $table->yearsAvailable, $name);
            self::assertSame('CC BY 4.0', $table->attribution['licence'] ?? null, "$name: licence");
            self::assertNotEmpty($table->sources, "$name: sources");
        }
    }

    public function testSectionsAreWellFormed(): void
    {
        foreach (self::allTables() as $name => $table) {
            $ids = [];
            foreach ($table->sections as $s) {
                $where = "$name section {$s->id}";
                self::assertNotSame('', $s->id, $where);
                self::assertNotContains($s->id, $ids, "$where: unique id");
                $ids[] = $s->id;
                self::assertContains($s->group, ['income-tax', 'employee', 'employer', 'other'], $where);
                self::assertContains($s->kind, ['bands', 'values'], $where);
                self::assertMatchesRegularExpression(self::URL, $s->source->url, "$where: source url");
                self::assertMatchesRegularExpression(self::DATE, $s->source->retrievedAt, "$where: retrievedAt");
                self::assertNotEmpty($s->rows, "$where: rows");
                if ($s->isBands()) {
                    $previousFrom = -INF;
                    foreach ($s->rows as $i => $row) {
                        self::assertTrue(is_int($row['from']) || is_float($row['from']), "$where band $i: from");
                        self::assertTrue(is_int($row['rate']) || is_float($row['rate']), "$where band $i: rate");
                        self::assertTrue($row['rate'] >= 0 && $row['rate'] <= 1, "$where band $i: rate is a decimal");
                        self::assertGreaterThanOrEqual($previousFrom, $row['from'], "$where band $i: ascending");
                        if ($row['to'] !== null) {
                            self::assertGreaterThan($row['from'], $row['to'], "$where band $i: to > from");
                        }
                        $previousFrom = $row['from'];
                    }
                    self::assertIsFloat($s->taxOn(1000000), $where);
                } else {
                    foreach ($s->rows as $i => $row) {
                        self::assertIsString($row['label'] ?? null, "$where value $i: label");
                        self::assertContains($row['unit'] ?? null, ['rate', 'money', 'number', 'text'], "$where value $i: unit");
                    }
                }
            }
            self::assertCount(count($ids), array_unique($ids));
        }
    }
}
