<?php

declare(strict_types=1);

namespace CalcBloc\Tests;

use CalcBloc\CalcBlocException;
use CalcBloc\Client;
use CalcBloc\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

/** API 1.8 and 1.9 endpoints: each method calls its path with the API's parameter names. No network. */
final class Endpoints19Test extends TestCase
{
    private const ATTRIBUTION = ['source' => 'CalcBloc', 'url' => 'https://calcbloc.com/developers', 'required' => true, 'text' => 'Data by CalcBloc (calcbloc.com)'];

    public function testEachMethodCallsItsEndpoint(): void
    {
        $t = new FakeTransport();
        for ($i = 0; $i < 9; $i++) {
            $t->queue(200, ['attribution' => self::ATTRIBUTION]);
        }
        $c = new Client(apiKey: 'k', transport: $t);
        $c->supplementalWithholding('us', 100000, 10000, ['type' => 'bonus', 'region' => 'ca']);
        $c->equityTax('uk', 'emi', 1000, '2024-01-15', '2026-03-01', ['fmv_event' => 12, 'exercise_price' => 2]);
        $c->crossBorderEquity('rsu', 100, '2024-01-01', '2026-01-01', 'us:2024-01-01:2024-12-31,gb:2025-01-01:2026-01-01', ['us_citizen' => false]);
        $c->facts(['country' => 'uk', 'tax_type' => 'income.pit']);
        $c->taxCalendar('uk');
        $c->taxCalendar();
        $c->tariffs('gb', '0901', ['origin' => 'in']);
        $c->tradeAgreements('gb', 'in');
        $res = $c->importDuty('gb', 'in', '0901210000', 1000, ['proof_of_origin' => true]);
        self::assertSame(self::ATTRIBUTION, $res['attribution']);
        $base = 'https://calcbloc.com/api/v1/';
        self::assertSame([
            $base . 'supplemental-withholding?country=us&salary=100000&amount=10000&type=bonus&region=ca',
            $base . 'equity-tax?country=uk&instrument=emi&units=1000&grant_date=2024-01-15&event_date=2026-03-01&fmv_event=12&exercise_price=2',
            $base . 'cross-border-equity?instrument=rsu&units=100&grant_date=2024-01-01&event_date=2026-01-01&periods=us%3A2024-01-01%3A2024-12-31%2Cgb%3A2025-01-01%3A2026-01-01&us_citizen=false',
            $base . 'facts?country=uk&tax_type=income.pit',
            $base . 'tax-calendar?country=uk',
            $base . 'tax-calendar',
            $base . 'tariffs?destination=gb&code=0901&origin=in',
            $base . 'trade-agreements?country=gb&partner=in',
            $base . 'import-duty?destination=gb&origin=in&code=0901210000&customs_value=1000&proof_of_origin=true',
        ], array_column($t->requests, 'url'));
    }

    public function testApiErrorsAreThrown(): void
    {
        $t = (new FakeTransport())->queue(400, ['error' => ['code' => 'invalid_parameter', 'message' => 'Code 610910 matches 2 tariff lines'], 'attribution' => self::ATTRIBUTION]);
        $c = new Client(apiKey: 'k', transport: $t);
        try {
            $c->importDuty('gb', 'cn', '610910', 100);
            self::fail('expected an exception');
        } catch (CalcBlocException $e) {
            self::assertSame('invalid_parameter', $e->getErrorCode());
        }
    }
}
