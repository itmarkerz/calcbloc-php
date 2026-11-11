<?php

declare(strict_types=1);

namespace CalcBloc\Tests;

use CalcBloc\Section;
use CalcBloc\Tables;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tax computed with Tables::taxFromBands() from the bundled tables, compared with figures the tax authorities
 * publish themselves. Each case names its source; expected values are copied from that source, not computed here.
 */
final class OfficialWorkedExamplesTest extends TestCase
{
    private static function bands(string $country, string $year, string $sectionId): Section
    {
        $info = Tables::country($country);
        if ($info === null || !in_array($year, $info->years, true)) {
            self::markTestSkipped("$country $year is no longer bundled; update this example to a published example for a bundled year.");
        }
        $section = Tables::load($country, $year)->section($sectionId);
        self::assertNotNull($section, "$country $year has no section $sectionId");
        self::assertTrue($section->isBands());

        return $section;
    }

    /**
     * Ireland, Universal Social Charge 2026.
     * Revenue, "Calculating your USC": https://www.revenue.ie/en/jobs-and-pensions/usc/calculating-usc.aspx
     * (Examples 1, 2 and 4 use the standard rates; Example 3 the reduced rates for a medical card holder).
     *
     * @return iterable<string, array{string, int, float}>
     */
    public static function irelandUsc(): iterable
    {
        yield 'Example 1, Jacob: EUR 25,000 -> EUR 319.82' => ['usc', 25000, 319.82];
        yield 'Example 2, Sadhbh: EUR 50,000 -> EUR 1,032.82' => ['usc', 50000, 1032.82];
        yield 'Example 3, Donnchadh (full medical card, reduced rates): EUR 50,000 -> EUR 819.82' => ['usc-reduced', 50000, 819.82];
        yield 'Example 4, Cian (over EUR 60,000, standard rates): EUR 75,000 -> EUR 2,030.62' => ['usc', 75000, 2030.62];
    }

    #[DataProvider('irelandUsc')]
    public function testIrelandUscRevenueExamples(string $sectionId, int $income, float $expected): void
    {
        $section = self::bands('ireland', '2026', $sectionId);
        self::assertSame($expected, round(Tables::taxFromBands($section, $income), 2));
    }

    public function testIrelandUscExample4BandByBand(): void
    {
        // Revenue Example 4 prints each band: 60.06 + 333.76 + 1,240.32 (3% on 41,344) + 396.48 (8% on 4,956).
        $section = self::bands('ireland', '2026', 'usc');
        $byBand = [];
        $previous = 0.0;
        foreach ($section->rows as $row) {
            $top = $row['to'] === null ? 75000 : min(75000, $row['to']);
            $tax = Tables::taxFromBands($section, $top);
            $byBand[] = round($tax - $previous, 2);
            $previous = $tax;
        }
        self::assertSame([60.06, 333.76, 1240.32, 396.48], $byBand);
    }

    /**
     * Singapore, resident income tax rates from Year of Assessment 2024 (income earned in 2026 is assessed in YA 2027).
     * IRAS, "Individual Income Tax rates", Gross Tax Payable column:
     * https://www.iras.gov.sg/taxes/individual-income-tax/basics-of-individual-income-tax/tax-residency-and-tax-rates/individual-income-tax-rates
     *
     * @return iterable<string, array{int, int}>
     */
    public static function singaporeGrossTaxPayable(): iterable
    {
        $published = [
            20000 => 0,
            30000 => 200,
            40000 => 550,
            80000 => 3350,
            120000 => 7950,
            160000 => 13950,
            200000 => 21150,
            240000 => 28750,
            280000 => 36550,
            320000 => 44550,
            500000 => 84150,
            1000000 => 199150,
        ];
        foreach ($published as $income => $tax) {
            yield sprintf('first SGD %s -> SGD %s', number_format($income), number_format($tax)) => [$income, $tax];
        }
    }

    #[DataProvider('singaporeGrossTaxPayable')]
    public function testSingaporeIrasGrossTaxPayable(int $chargeableIncome, int $expected): void
    {
        $section = self::bands('singapore', '2026', 'income-tax');
        self::assertEqualsWithDelta($expected, Tables::taxFromBands($section, $chargeableIncome), 0.005);
    }

    /**
     * United States, federal income tax 2026. IRS Publication 15-T (2026), "2026 Percentage Method Tables for
     * Automated Payroll Systems" (Section 1, p. 12): https://www.irs.gov/pub/irs-pdf/p15t.pdf
     *
     * Each row of the STANDARD withholding tables prints the tax at the start of a bracket ("tentative amount to
     * withhold", column C). The table's wage thresholds are the Rev. Proc. 2025-32 brackets shifted by a fixed
     * amount (Single 7,500; Married filing jointly 19,300; Head of household 15,550), so the tax on taxable income
     * equal to (threshold - shift) must be that printed amount.
     *
     * @return iterable<string, array{string, int, int, float}>
     */
    public static function usPub15t(): iterable
    {
        $tables = [
            'federal-income-tax-single' => [7500, [7500 => 0, 19900 => 1240, 57900 => 5800, 113200 => 17966, 209275 => 41024, 263725 => 58448, 648100 => 192979.25]],
            'federal-income-tax-mfj' => [19300, [19300 => 0, 44100 => 2480, 120100 => 11600, 230700 => 35932, 422850 => 82048, 531750 => 116896, 788000 => 206583.50]],
            'federal-income-tax-hoh' => [15550, [15550 => 0, 33250 => 1770, 83000 => 7740, 121250 => 16155, 217300 => 39207, 271750 => 56631, 656150 => 191171]],
        ];
        foreach ($tables as $section => [$shift, $rows]) {
            foreach ($rows as $threshold => $base) {
                yield sprintf('%s: wages %s -> USD %s', $section, number_format($threshold), number_format($base, 2)) => [$section, $shift, $threshold, (float) $base];
            }
        }
    }

    #[DataProvider('usPub15t')]
    public function testUsFederalBracketsMatchPub15tAmounts(string $sectionId, int $shift, int $threshold, float $expected): void
    {
        $section = self::bands('us', '2026', $sectionId);
        self::assertEqualsWithDelta($expected, Tables::taxFromBands($section, $threshold - $shift), 0.005);
    }

    /**
     * New Zealand, income tax 2026-27. Inland Revenue, "Payroll calculations and business rules specification"
     * for 1 April 2026 to 31 March 2027, section 5.2, gives annual tax as one formula per bracket:
     * 10.5% x income; 17.5% x income - 1,092.00; 30% x income - 7,779.50; 33% x income - 10,122.50;
     * 39% x income - 20,922.50.
     * https://www.ird.govt.nz/-/media/project/ir/home/documents/digital-service-providers/software-providers/payroll-calculations-business-rules-specifications/payroll-calculations-and-business-rules-specification.pdf
     *
     * @return iterable<string, array{int, float}>
     */
    public static function newZealandIrdFormulas(): iterable
    {
        $formula = static function (int $income): float {
            return match (true) {
                $income <= 15600 => 0.105 * $income,
                $income <= 53500 => 0.175 * $income - 1092.00,
                $income <= 78100 => 0.30 * $income - 7779.50,
                $income <= 180000 => 0.33 * $income - 10122.50,
                default => 0.39 * $income - 20922.50,
            };
        };
        foreach ([15000, 15600, 52000, 53500, 70000, 78100, 120000, 180000, 250000] as $income) {
            yield sprintf('NZD %s', number_format($income)) => [$income, $formula($income)];
        }
    }

    #[DataProvider('newZealandIrdFormulas')]
    public function testNewZealandIrdPayeFormulas(int $income, float $expected): void
    {
        $section = self::bands('new-zealand', '2026-27', 'income-tax');
        self::assertEqualsWithDelta($expected, Tables::taxFromBands($section, $income), 0.005);
    }
}
