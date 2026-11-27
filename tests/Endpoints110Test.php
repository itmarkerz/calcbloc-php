<?php

declare(strict_types=1);

namespace CalcBloc\Tests;

use CalcBloc\CalcBlocException;
use CalcBloc\Client;
use CalcBloc\Internal\CountryCodes;
use CalcBloc\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

/**
 * API 1.10: /tax-treaties, the verification field, and Denmark, Estonia and Poland. No network: the fixtures are real
 * API 1.10.0 responses, produced by the site's own request handler.
 */
final class Endpoints110Test extends TestCase
{
    /** @return array<string, mixed> */
    private static function fixture(string $name): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents(__DIR__ . "/fixtures/api-1.10/$name.json"), true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    public function testTaxTreaties(): void
    {
        $t = (new FakeTransport())->queue(200, self::fixture('tax-treaties-in-us'))->queue(200, self::fixture('tax-treaties-in-us'));
        $c = new Client(apiKey: 'k', transport: $t);
        $res = $c->taxTreaties('in', 'us');
        $c->taxTreaties('India');
        self::assertSame([
            'https://calcbloc.com/api/v1/tax-treaties?country=in&partner=us',
            'https://calcbloc.com/api/v1/tax-treaties?country=India',
        ], array_column($t->requests, 'url'));
        self::assertSame('in', $res['country']);
        self::assertSame('us', $res['partner']);
        self::assertSame(1, $res['count']);
        /** @var list<array<string, mixed>> $treaties */
        $treaties = $res['treaties'];
        $us = $treaties[0];
        self::assertSame(['in', 'us'], $us['countries']);
        self::assertSame('United States', $us['partner_name']);
        self::assertSame('1989-09-12', $us['signed']);
        self::assertSame('1990-12-18', $us['in_force']);
        self::assertSame(['in' => 'credit', 'us' => 'credit'], $us['relief_method']);
        /** @var list<array<string, mixed>> $withholding */
        $withholding = $us['withholding'];
        self::assertSame('dividend_substantial', $withholding[0]['kind']);
        self::assertSame(0.15, $withholding[0]['rate']);
        self::assertSame('Article 10(2)(a)', $withholding[0]['article']);
        /** @var array<string, mixed> $source */
        $source = $us['source'];
        self::assertSame('https://www.irs.gov/pub/irs-trty/india.pdf', $source['url']);
    }

    public function testTaxTreatiesUnknownCountryThrows(): void
    {
        $t = (new FakeTransport())->queue(400, ['error' => ['code' => 'unsupported_country', 'message' => 'No treaties are stored for "de". Countries: in.']]);
        $c = new Client(apiKey: 'k', transport: $t);
        try {
            $c->taxTreaties('de');
            self::fail('expected an exception');
        } catch (CalcBlocException $e) {
            self::assertSame('unsupported_country', $e->getErrorCode());
        }
    }

    public function testVerificationOnCalculations(): void
    {
        $t = (new FakeTransport())
            ->queue(200, self::fixture('net-salary-pl'))
            ->queue(200, self::fixture('net-salary-dk'))
            ->queue(200, self::fixture('employer-cost-ee'))
            ->queue(200, self::fixture('net-salary-uk'));
        $c = new Client(apiKey: 'k', transport: $t);
        $pl = $c->netSalary('pl', 120000, ['accident_rate_pct' => 1.67, 'commuter' => false]);
        $dk = $c->netSalary('dk', 600000, ['municipality' => 'average', 'church' => false, 'atp_hours' => '0']);
        $ee = $c->employerCost('ee', 30000, ['funded_pension' => '0', 'pension_age' => false]);
        $uk = $c->netSalary('uk', 50000);
        $base = 'https://calcbloc.com/api/v1/';
        self::assertSame($base . 'net-salary?country=pl&salary=120000&accident_rate_pct=1.67&commuter=false', $t->requests[0]['url']);
        self::assertSame($base . 'net-salary?country=dk&salary=600000&municipality=average&church=false&atp_hours=0', $t->requests[1]['url']);
        self::assertSame('official-example', $pl->verification);
        self::assertStringStartsWith('Verification (official-example)', $pl->notes[0]);
        self::assertSame('PLN', $pl->currency);
        self::assertSame('law-text', $dk->verification);
        self::assertSame('official-example', $ee->verification);
        self::assertSame('employer-cost', $ee->endpoint);
        self::assertGreaterThan(30000, $ee->summary['totalCost']);
        self::assertNull($uk->verification);
    }

    public function testCountriesVerificationPerEndpoint(): void
    {
        $t = (new FakeTransport())->queue(200, self::fixture('countries-1.10-sample'));
        $c = new Client(apiKey: 'k', transport: $t);
        $byCode = [];
        foreach ($c->countries() as $country) {
            $byCode[$country->code] = $country;
        }
        self::assertSame('law-text', $byCode['denmark']->verification('net-salary'));
        self::assertSame('official-example', $byCode['poland']->verification('employer-cost'));
        self::assertSame('official-example', $byCode['estonia']->verification('net-salary'));
        self::assertNull($byCode['uk']->verification('net-salary'));
        self::assertNull($byCode['fiji']->verification('net-salary'));
        self::assertTrue($byCode['poland']->supports('employer-cost'));
    }

    public function testCountryCodesCoverApi110(): void
    {
        foreach (['dk' => 'denmark', 'EE' => 'estonia', 'Poland' => 'poland', 'fj' => 'fiji', 'Faroe Islands' => 'faroe-islands', 'gl' => 'greenland', 'ky' => 'cayman-islands'] as $given => $key) {
            self::assertSame($key, CountryCodes::key((string) $given), (string) $given);
        }
    }
}
