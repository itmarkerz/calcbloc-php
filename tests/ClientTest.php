<?php

declare(strict_types=1);

namespace CalcBloc\Tests;

use CalcBloc\CalcBlocException;
use CalcBloc\Client;
use CalcBloc\Http\Psr18Transport;
use CalcBloc\RateLimitException;
use CalcBloc\Tables;
use CalcBloc\TaxTable;
use CalcBloc\Tests\Support\FakeTransport;
use CalcBloc\TransportException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The API client against a fake transport. Response bodies follow the API's documented shapes
 * (https://calcbloc.com/api/openapi.json); nothing here touches the network.
 */
final class ClientTest extends TestCase
{
    private const RATE_HEADERS = ['X-RateLimit-Limit' => '5000', 'X-RateLimit-Remaining' => '4999', 'X-RateLimit-Reset' => '3600'];

    private const ATTRIBUTION = ['source' => 'CalcBloc', 'url' => 'https://calcbloc.com/developers', 'required' => true, 'text' => 'Data by CalcBloc (calcbloc.com)'];

    private function client(FakeTransport $transport, ?string $key = 'test-key'): Client
    {
        return new Client($key, 'https://calcbloc.com', 7.5, $transport);
    }

    public function testCountries(): void
    {
        $transport = (new FakeTransport())->queue(200, [
            'countries' => [
                ['code' => 'us', 'name' => 'United States', 'currency' => 'USD', 'years' => ['2026'], 'current_year' => '2026', 'years_available' => ['2026'],
                    'available_endpoints' => ['net-salary', 'employer-cost', 'compare', 'tax-tables'], 'tables_only' => false,
                    'endpoints' => ['net-salary' => ['path' => '/api/v1/net-salary?country=us', 'parameters' => []]]],
                ['code' => 'norway', 'name' => 'Norway', 'currency' => 'NOK', 'years' => ['2026'], 'current_year' => '2026', 'years_available' => ['2026'],
                    'available_endpoints' => ['tax-tables'], 'tables_only' => true, 'endpoints' => []],
            ],
            'attribution' => self::ATTRIBUTION,
        ], self::RATE_HEADERS);
        $countries = $this->client($transport)->countries();

        self::assertSame('https://calcbloc.com/api/v1/countries', $transport->last()['url']);
        self::assertCount(2, $countries);
        self::assertSame('us', $countries[0]->code);
        self::assertTrue($countries[0]->supports('net-salary'));
        self::assertTrue($countries[1]->tablesOnly);
        self::assertFalse($countries[1]->supports('net-salary'));
    }

    public function testRequestHeaders(): void
    {
        $transport = (new FakeTransport())->queue(200, ['countries' => []])->queue(200, ['countries' => []]);
        $this->client($transport, 'my-key')->countries();
        $headers = $transport->last()['headers'];
        self::assertSame('my-key', $headers['X-API-Key']);
        self::assertSame('application/json', $headers['Accept']);
        self::assertStringStartsWith('calcbloc-php/', $headers['User-Agent']);
        self::assertSame(7.5, $transport->last()['timeout']);

        $this->client($transport, null)->countries();
        self::assertArrayNotHasKey('X-API-Key', $transport->last()['headers'], 'no key header without a key');
    }

    public function testTaxTablesReturnsTheSameObjectAsTheOfflineLoader(): void
    {
        $c = Tables::listCountries()[0];
        $offline = Tables::load($c->country);
        $transport = (new FakeTransport())->queue(200, $offline->toArray(), self::RATE_HEADERS);
        $client = $this->client($transport);
        $table = $client->taxTables($c->country);

        self::assertSame("https://calcbloc.com/api/v1/tax-tables/{$c->country}/latest", $transport->last()['url']);
        self::assertInstanceOf(TaxTable::class, $table);
        self::assertSame($offline->toArray(), $table->toArray());
        self::assertNotNull($client->lastRateLimit());
        self::assertSame(4999, $client->lastRateLimit()->remaining);
    }

    public function testTaxTablesRegionAndCsv(): void
    {
        $csv = "section_id,section,group\r\nincome-tax,Income tax,income-tax\r\n";
        $transport = (new FakeTransport())->queue(200, $csv);
        $out = $this->client($transport)->taxTables('Canada', '2026', 'on', 'csv');

        self::assertSame($csv, $out);
        self::assertSame('https://calcbloc.com/api/v1/tax-tables/canada/2026?region=on&format=csv', $transport->last()['url']);
        self::assertSame('text/csv', $transport->last()['headers']['Accept']);
    }

    public function testNetSalaryAndEmployerCost(): void
    {
        $body = [
            'endpoint' => 'net-salary', 'country' => 'uk', 'countryName' => 'United Kingdom', 'year' => '2026-27', 'requested_year' => 'latest',
            'currency' => 'GBP', 'inputs' => ['salary' => 50000, 'region' => 'scotland'],
            'summary' => ['grossAnnual' => 50000, 'netAnnual' => 38000.5, 'totalDeductions' => 11999.5, 'effectiveRate' => 0.24],
            'results' => ['incomeTax' => 8000.5], 'breakdown' => null, 'notes' => ['A note.'],
            'calculatorUrl' => 'https://calcbloc.com/uk/salary-calculator', 'warnings' => [], 'attribution' => self::ATTRIBUTION,
        ];
        $transport = (new FakeTransport())->queue(200, $body)->queue(200, ['endpoint' => 'employer-cost', 'summary' => ['totalCost' => 60000]]);
        $client = $this->client($transport);

        $result = $client->netSalary('uk', 50000, ['region' => 'scotland', 'student_loan' => false, 'ignored' => null]);
        self::assertSame('https://calcbloc.com/api/v1/net-salary?country=uk&salary=50000&region=scotland&student_loan=false', $transport->last()['url']);
        self::assertSame('2026-27', $result->year);
        self::assertSame('latest', $result->requestedYear);
        self::assertSame(38000.5, $result->summary['netAnnual']);
        self::assertSame(['A note.'], $result->notes);
        self::assertSame($body, $result->data);

        $cost = $client->employerCost('germany', 65000.5);
        self::assertSame('https://calcbloc.com/api/v1/employer-cost?country=germany&salary=65000.5', $transport->last()['url']);
        self::assertSame(60000, $cost->summary['totalCost']);
    }

    public function testCompare(): void
    {
        $transport = (new FakeTransport())->queue(200, [
            'endpoint' => 'compare', 'inputs' => ['a' => 'uk', 'b' => 'ireland', 'salary' => 60000, 'currency' => 'GBP'],
            'a' => ['country' => 'uk', 'net' => 45000], 'b' => ['country' => 'ireland', 'net' => 50000],
            'exchangeRate' => ['GBP_EUR' => 1.17], 'difference' => ['inACurrency' => 1000], 'interpretation' => 'Text.',
            'calculatorUrl' => 'https://calcbloc.com/compare/salary', 'warnings' => [],
        ]);
        $result = $this->client($transport)->compare('uk', 'ireland', 60000, ['currency' => 'GBP']);
        self::assertSame('https://calcbloc.com/api/v1/compare?a=uk&b=ireland&salary=60000&currency=GBP', $transport->last()['url']);
        self::assertSame(45000, $result->a['net']);
        self::assertSame('Text.', $result->interpretation);
    }

    public function testTablesOnlyCountryRaisesUnsupportedCountry(): void
    {
        $transport = (new FakeTransport())->queue(400, ['error' => [
            'code' => 'unsupported_country',
            'message' => 'net-salary is not available for Norway yet. Its tax tables are at https://calcbloc.com/norway/tax-tables/2026 (API: /api/v1/tax-tables/norway/2026).',
            'docs' => 'https://calcbloc.com/developers',
        ], 'attribution' => self::ATTRIBUTION], self::RATE_HEADERS);
        try {
            $this->client($transport)->netSalary('norway', 700000);
            self::fail('expected an exception');
        } catch (CalcBlocException $e) {
            self::assertNotInstanceOf(RateLimitException::class, $e);
            self::assertSame('unsupported_country', $e->getErrorCode());
            self::assertSame(400, $e->getStatus());
            self::assertSame(400, $e->getCode());
            self::assertSame('https://calcbloc.com/developers', $e->getDocs());
            self::assertStringContainsString('not available for Norway', $e->getMessage());
            self::assertSame('5000', $e->getHeaders()['x-ratelimit-limit']);
        }
    }

    public function testMissingKey(): void
    {
        $transport = (new FakeTransport())->queue(401, ['error' => ['code' => 'api_key_required', 'message' => 'This endpoint needs a free API key: send it in the X-API-Key header.', 'docs' => 'https://calcbloc.com/developers/keys']]);
        $this->expectException(CalcBlocException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('X-API-Key');
        $this->client($transport, null)->netSalary('us', 80000);
    }

    public function testRateLimit(): void
    {
        $transport = (new FakeTransport())->queue(429, ['error' => [
            'code' => 'rate_limited', 'message' => 'Too many requests this minute (limit 60).', 'docs' => 'https://calcbloc.com/developers#limits',
        ]], ['Retry-After' => '42', 'X-RateLimit-Limit' => '60', 'X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => '42']);
        try {
            $this->client($transport)->taxTables('us');
            self::fail('expected an exception');
        } catch (RateLimitException $e) {
            self::assertSame('rate_limited', $e->getErrorCode());
            self::assertSame(429, $e->getStatus());
            self::assertSame(42, $e->getRetryAfter());
            self::assertNotNull($e->getRateLimit());
            self::assertSame(0, $e->getRateLimit()->remaining);
            self::assertSame('https://calcbloc.com/developers#limits', $e->getDocs());
        }
    }

    public function testNonJsonErrorBody(): void
    {
        $transport = (new FakeTransport())->queue(502, '<html>Bad gateway</html>');
        try {
            $this->client($transport)->countries();
            self::fail('expected an exception');
        } catch (CalcBlocException $e) {
            self::assertSame('http_error', $e->getErrorCode());
            self::assertSame(502, $e->getStatus());
        }
    }

    public function testInvalidJsonSuccessBody(): void
    {
        $transport = (new FakeTransport())->queue(200, 'not json');
        $this->expectException(CalcBlocException::class);
        $this->client($transport)->countries();
    }

    public function testRefusesKeyInQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client(new FakeTransport())->netSalary('us', 1, ['key' => 'secret']);
    }

    public function testRejectsBadFormatAndBaseUrl(): void
    {
        try {
            $this->client(new FakeTransport())->taxTables('us', 'latest', null, 'xml');
            self::fail('expected an exception');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(\InvalidArgumentException::class);
        new Client(null, 'calcbloc.com');
    }

    public function testCustomBaseUrl(): void
    {
        $transport = (new FakeTransport())->queue(200, ['countries' => []]);
        (new Client(null, 'http://localhost:3030/', 5, $transport))->countries();
        self::assertSame('http://localhost:3030/api/v1/countries', $transport->last()['url']);
    }

    public function testPsr18Transport(): void
    {
        $factory = new Psr17Factory();
        $psrClient = new class () implements ClientInterface {
            public ?RequestInterface $request = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Psr7Response(200, ['Content-Type' => 'application/json', 'X-RateLimit-Limit' => '50', 'X-RateLimit-Remaining' => '49', 'X-RateLimit-Reset' => '86000'], '{"countries":[]}');
            }
        };
        $client = new Client('k', transport: new Psr18Transport($psrClient, $factory));
        self::assertSame([], $client->countries());
        self::assertNotNull($psrClient->request);
        self::assertSame('k', $psrClient->request->getHeaderLine('X-API-Key'));
        self::assertSame('https://calcbloc.com/api/v1/countries', (string) $psrClient->request->getUri());
        self::assertSame(49, $client->lastRateLimit()?->remaining);
    }

    public function testPsr18ClientErrorBecomesTransportException(): void
    {
        $psrClient = new class () implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class ('connection refused') extends \RuntimeException implements ClientExceptionInterface {
                };
            }
        };
        try {
            (new Client(null, transport: new Psr18Transport($psrClient, new Psr17Factory())))->countries();
            self::fail('expected an exception');
        } catch (TransportException $e) {
            self::assertSame('transport_error', $e->getErrorCode());
            self::assertNull($e->getStatus());
            self::assertStringContainsString('connection refused', $e->getMessage());
        }
    }
}
