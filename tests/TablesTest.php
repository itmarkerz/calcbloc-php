<?php

declare(strict_types=1);

namespace CalcBloc\Tests;

use CalcBloc\CalcBlocException;
use CalcBloc\CountryInfo;
use CalcBloc\Section;
use CalcBloc\Source;
use CalcBloc\Tables;
use CalcBloc\TaxTable;
use PHPUnit\Framework\TestCase;

/**
 * The offline loader. Countries, years and regions always come from the bundled manifest: no country list here.
 */
final class TablesTest extends TestCase
{
    protected function tearDown(): void
    {
        Tables::useDirectory(null);
    }

    public function testManifestCarriesLicenceAndCredit(): void
    {
        $manifest = Tables::manifest();
        self::assertSame('CC BY 4.0', $manifest['licence']);
        self::assertSame('Data by CalcBloc (calcbloc.com/data)', $manifest['credit']);
        self::assertSame('https://calcbloc.com/data', $manifest['source']);
        self::assertIsArray($manifest['countries']);
        self::assertNotEmpty($manifest['countries']);
    }

    public function testListCountriesMatchesManifest(): void
    {
        $countries = Tables::listCountries();
        $manifest = Tables::manifest();
        self::assertIsArray($manifest['countries']);
        self::assertCount(count($manifest['countries']), $countries);
        $codes = array_map(static fn (CountryInfo $c): string => $c->country, $countries);
        self::assertSame(array_values(array_unique($codes)), $codes, 'country codes are unique');
        foreach ($countries as $c) {
            self::assertMatchesRegularExpression('~^[a-z0-9-]+$~', $c->country);
            self::assertMatchesRegularExpression('~^[A-Z]{3}$~', $c->currency);
            self::assertContains($c->currentYear, $c->years);
            self::assertStringStartsWith('https://calcbloc.com/', $c->pageUrl);
            self::assertStringStartsWith('https://calcbloc.com/api/v1/tax-tables/' . $c->country . '/', $c->apiUrl);
            self::assertMatchesRegularExpression('~^\d{4}-\d{2}-\d{2}$~', $c->lastVerified);
            foreach ($c->files as $file) {
                self::assertFileExists(Tables::directory() . '/' . $file);
            }
            self::assertContains($c->country . '/' . $c->currentYear . '.json', $c->files, "{$c->country}: national file for the current year");
        }
    }

    public function testLoadLatestEqualsCurrentYear(): void
    {
        foreach (Tables::listCountries() as $c) {
            $latest = Tables::load($c->country);
            self::assertSame($c->currentYear, $latest->year);
            self::assertSame($latest, Tables::load(strtoupper($c->country), 'latest'), 'cached and case-insensitive');
            self::assertSame($c->country, $latest->country);
            self::assertSame($c->currency, $latest->currency);
            self::assertNull($latest->region);
            self::assertNotEmpty($latest->sections);
        }
    }

    public function testEveryBundledYearAndRegionLoads(): void
    {
        $loaded = 0;
        foreach (Tables::listCountries() as $c) {
            foreach ($c->years as $year) {
                $table = Tables::load($c->country, $year);
                self::assertSame($year, $table->year);
                foreach ($c->regions as $region) {
                    if (!in_array("{$c->country}/$year/{$region->code}.json", $c->files, true)) {
                        continue;
                    }
                    $regional = Tables::load($c->country, $year, $region->code);
                    self::assertNotNull($regional->region);
                    self::assertSame($region->code, $regional->region->code);
                    self::assertSame($regional, Tables::load($c->country, $year, $region->slug), 'slug resolves to the same table');
                    $loaded++;
                }
                $loaded++;
            }
        }
        $files = array_sum(array_map(static fn (CountryInfo $c): int => count($c->files), Tables::listCountries()));
        self::assertSame($files, $loaded, 'every bundled file is reachable through load()');
    }

    public function testTypedSections(): void
    {
        $c = Tables::listCountries()[0];
        $table = Tables::load($c->country);
        self::assertInstanceOf(TaxTable::class, $table);
        foreach ($table->sections as $section) {
            self::assertInstanceOf(Section::class, $section);
            self::assertInstanceOf(Source::class, $section->source);
            self::assertSame($section, $table->section($section->id));
            self::assertContains($section, $table->sectionsInGroup($section->group));
        }
        self::assertNull($table->section('no-such-section'));
    }

    public function testUnknownCountry(): void
    {
        try {
            Tables::load('atlantis');
            self::fail('expected an exception');
        } catch (CalcBlocException $e) {
            self::assertSame('unsupported_country', $e->getErrorCode());
            self::assertNull($e->getStatus());
        }
    }

    public function testUnknownYear(): void
    {
        $c = Tables::listCountries()[0];
        $this->expectException(CalcBlocException::class);
        $this->expectExceptionMessage('Years: ' . implode(', ', $c->years));
        Tables::load($c->country, '1999');
    }

    public function testUnknownRegion(): void
    {
        $withRegions = null;
        $withoutRegions = null;
        foreach (Tables::listCountries() as $c) {
            if ($c->regions !== []) {
                $withRegions ??= $c;
            } else {
                $withoutRegions ??= $c;
            }
        }
        self::assertNotNull($withRegions);
        self::assertNotNull($withoutRegions);
        foreach ([[$withRegions->country, 'zz-nowhere'], [$withoutRegions->country, 'anything']] as [$country, $region]) {
            try {
                Tables::load($country, null, $region);
                self::fail('expected an exception');
            } catch (CalcBlocException $e) {
                self::assertSame('unsupported_region', $e->getErrorCode());
            }
        }
    }

    public function testTaxFromBands(): void
    {
        $section = [
            'id' => 'test',
            'title' => 'Test bands',
            'group' => 'income-tax',
            'kind' => 'bands',
            'rows' => [
                ['from' => 0, 'to' => 10000, 'rate' => 0],
                ['from' => 10000, 'to' => 50000, 'rate' => 0.2],
                ['from' => 50000, 'to' => null, 'rate' => 0.4],
            ],
            'source' => ['title' => 't', 'url' => 'https://example.org', 'retrievedAt' => '2026-01-01'],
            'verified' => true,
        ];
        self::assertSame(0.0, Tables::taxFromBands($section, -5));
        self::assertSame(0.0, Tables::taxFromBands($section, 0));
        self::assertSame(0.0, Tables::taxFromBands($section, 10000));
        self::assertEqualsWithDelta(8000.0, Tables::taxFromBands($section, 50000), 1e-9);
        self::assertEqualsWithDelta(12000.0, Tables::taxFromBands($section, 60000), 1e-9);
        self::assertEqualsWithDelta(12000.0, Section::fromArray($section)->taxOn(60000), 1e-9);
    }

    public function testTaxFromBandsRejectsValueSections(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Tables::taxFromBands(['id' => 'x', 'kind' => 'values', 'rows' => [['label' => 'Rate', 'value' => 0.1, 'unit' => 'rate']]], 1000);
    }

    public function testUseDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/calcbloc-php-test-' . bin2hex(random_bytes(4));
        mkdir($dir . '/testland', 0777, true);
        file_put_contents($dir . '/manifest.json', json_encode(['countries' => [[
            'country' => 'testland', 'name' => 'Testland', 'currency' => 'TST', 'current_year' => '2030', 'years' => ['2030'],
            'regions' => [], 'files' => ['testland/2030.json'], 'pageUrl' => 'https://calcbloc.com/x', 'apiUrl' => 'https://calcbloc.com/api/v1/tax-tables/testland/2030',
            'lastVerified' => '2030-01-01', 'sourceUrls' => [], 'rowCount' => 1, 'underReviewRows' => 0,
        ]]]));
        file_put_contents($dir . '/testland/2030.json', json_encode([
            'country' => 'testland', 'countryName' => 'Testland', 'year' => '2030', 'region' => null, 'currency' => 'TST',
            'effectiveFrom' => '2030-01-01', 'effectiveTo' => '2030-12-31', 'status' => 'verified',
            'sections' => [[
                'id' => 'income-tax', 'title' => 'Income tax', 'group' => 'income-tax', 'kind' => 'bands',
                'rows' => [['from' => 0, 'to' => null, 'rate' => 0.1]],
                'source' => ['title' => 'Testland Tax Act', 'url' => 'https://example.org/act', 'retrievedAt' => '2030-01-01'],
                'verified' => true,
            ]],
            'sources' => [],
            'pageUrl' => 'https://calcbloc.com/x',
        ]));
        try {
            Tables::useDirectory($dir);
            self::assertSame(['testland'], array_map(static fn (CountryInfo $c): string => $c->country, Tables::listCountries()));
            $table = Tables::load('testland');
            self::assertCount(1, $table->rows, 'rows rebuilt from sections');
            self::assertSame('https://example.org/act', $table->rows[0]['source_url']);
            self::assertSame(['section_id', 'section', 'group', 'basis', 'note', 'tax_year', 'effective_from', 'effective_to', 'source_title', 'source_url', 'retrieved_at', 'status', 'label', 'from', 'to', 'rate', 'value', 'unit'], array_keys($table->rows[0]));
            self::assertSame(['rows', 'pageUrl'], array_slice(array_keys($table->toArray()), -2), 'rows placed before pageUrl');
        } finally {
            Tables::useDirectory(null);
            unlink($dir . '/testland/2030.json');
            rmdir($dir . '/testland');
            unlink($dir . '/manifest.json');
            rmdir($dir);
        }
    }
}
