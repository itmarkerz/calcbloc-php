<?php

declare(strict_types=1);

namespace CalcBloc;

use CalcBloc\Http\CurlTransport;
use CalcBloc\Http\Response;
use CalcBloc\Http\StreamTransport;
use CalcBloc\Http\Transport;
use CalcBloc\Internal\CountryCodes;
use CalcBloc\Internal\Json;

/**
 * Client for the CalcBloc API (https://calcbloc.com/developers).
 *
 * Every endpoint works without a key, within a free daily and per-minute allowance per IP address. A free API key
 * (email only), sent in the X-API-Key header, has much higher limits. The key comes from the constructor or, when
 * none is passed, from the CALCBLOC_API_KEY environment variable. When the free allowance without a key is used up,
 * RateLimitException says so and links to the keys page. Current limits: https://calcbloc.com/developers#limits.
 *
 *     $client = new CalcBloc\Client();                    // no key, or CALCBLOC_API_KEY from the environment
 *     $client = new CalcBloc\Client(apiKey: 'cb_...');    // a free key: much higher limits
 *     $table = $client->taxTables('canada', 'latest', region: 'on');
 *     $pay = $client->netSalary('nz', 80000);             // short codes (ISO alpha-2, `uk` for the UK) work too
 */
final class Client
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://calcbloc.com';
    /** Environment variable read for the API key when the constructor gets none. */
    public const API_KEY_ENV = 'CALCBLOC_API_KEY';
    /** Where to get a free API key; linked from the RateLimitException raised when the free allowance is used up. */
    public const KEYS_URL = 'https://calcbloc.com/developers/keys?utm_source=calcbloc-php&utm_medium=app&utm_campaign=itmarkerz-oss';

    private readonly ?string $apiKey;
    private readonly string $baseUrl;
    private readonly Transport $transport;
    private ?RateLimit $lastRateLimit = null;
    /** The daily request limit the API last reported (X-RateLimit-Limit), quoted in the rate-limit message. */
    private ?int $dailyLimit = null;

    /**
     * @param string|null $apiKey Your API key (free at https://calcbloc.com/developers/keys). Null reads the
     *                            CALCBLOC_API_KEY environment variable; '' (or neither set) calls without a key,
     *                            within the free allowance per IP address (https://calcbloc.com/developers#limits).
     * @param string $baseUrl API origin; the client calls {baseUrl}/api/v1/...
     * @param float $timeout Seconds before a request is abandoned.
     * @param Transport|null $transport HTTP transport; default: ext-curl when loaded, else PHP streams.
     */
    public function __construct(
        ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly float $timeout = 15.0,
        ?Transport $transport = null,
    ) {
        if (!preg_match('~^https?://[^/\s]+~i', $baseUrl)) {
            throw new \InvalidArgumentException(sprintf('Invalid base URL "%s".', $baseUrl));
        }
        if ($timeout <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than zero.');
        }
        $key = $apiKey ?? self::envApiKey();
        $this->apiKey = $key !== null && trim($key) !== '' ? trim($key) : null;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $transport ?? (extension_loaded('curl') ? new CurlTransport() : new StreamTransport());
    }

    /**
     * GET /api/v1/countries: every country with its tax years, regions, supported endpoints and their parameters.
     *
     * @return list<ApiCountry>
     */
    public function countries(): array
    {
        $data = $this->json('countries');
        $out = [];
        foreach ((array) ($data['countries'] ?? []) as $entry) {
            if (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $out[] = ApiCountry::fromArray($entry);
            }
        }

        return $out;
    }

    /**
     * GET /api/v1/tax-tables/{country}/{year}: official income tax brackets, thresholds, employee and employer rates
     * for a country and tax year, with the source of every figure.
     *
     * @param string $country Country: a short code (ISO 3166-1 alpha-2 in lower case, `uk` for the UK), e.g. `us`,
     *        `de`, `nz`, the English name, or the older key (`germany`, `new-zealand`).
     * @param string $year A tax year (e.g. `2026`, `2026-27`) or `latest`.
     * @param string|null $region Region code: US state (`ca`), Canadian province or territory (`on`), `scotland` or
     *        `wales`, and the other regions listed by countries(). Returns the regional figures only.
     * @param string $format `json` (default) returns a TaxTable; `csv` returns the CSV text (one row per band or value).
     *
     * @return ($format is 'csv' ? string : TaxTable)
     */
    public function taxTables(string $country, string $year = 'latest', ?string $region = null, string $format = 'json'): TaxTable|string
    {
        if ($format !== 'json' && $format !== 'csv') {
            throw new \InvalidArgumentException('Format must be "json" or "csv".');
        }
        $key = CountryCodes::key($country) ?? strtolower(trim($country));
        $path = 'tax-tables/' . rawurlencode($key) . '/' . rawurlencode(trim($year));
        $query = ['region' => $region !== null && trim($region) !== '' ? trim($region) : null];
        if ($format === 'csv') {
            $query['format'] = 'csv';

            return $this->request($path, $query, 'text/csv')->body;
        }

        return TaxTable::fromArray($this->json($path, $query));
    }

    /**
     * GET /api/v1/net-salary: take-home pay for an annual salary (no key needed within the free allowance).
     *
     * @param string $country Country with a calculation engine (see ApiCountry::$tablesOnly): short code (`nz`),
     *        English name or older key, as for taxTables().
     * @param int|float $salary Annual gross salary in the country's currency (India: annual CTC).
     * @param array<string, scalar|null> $params Other query parameters: `year`, `region` (required for `us` and
     *        `ca`) and the country's own parameters as listed by countries() (e.g. `filing_status` for the US:
     *        `single`, `mfj` or `hoh`).
     */
    public function netSalary(string $country, int|float $salary, array $params = []): CalculationResult
    {
        return CalculationResult::fromArray($this->json('net-salary', ['country' => $country, 'salary' => $salary] + $params));
    }

    /**
     * GET /api/v1/employer-cost: total cost of employing someone at an annual salary (no key needed within the free
     * allowance).
     *
     * @param array<string, scalar|null> $params Other query parameters, as for netSalary(); `region` is required
     *        for `us`, `au` and `ca`.
     */
    public function employerCost(string $country, int|float $salary, array $params = []): CalculationResult
    {
        return CalculationResult::fromArray($this->json('employer-cost', ['country' => $country, 'salary' => $salary] + $params));
    }

    /**
     * GET /api/v1/compare: the same salary in two countries (no key needed within the free allowance).
     *
     * @param array<string, scalar|null> $params `currency` (a's or b's), `us_state`, `uk_region`, `ca_province`, `in_state`.
     */
    public function compare(string $a, string $b, int|float $salary, array $params = []): ComparisonResult
    {
        return ComparisonResult::fromArray($this->json('compare', ['a' => $a, 'b' => $b, 'salary' => $salary] + $params));
    }

    // ----------------------------------------------------------------- API 1.8 and 1.9
    // These return the API's JSON as an array (every field documented at https://calcbloc.com/developers, plus the
    // attribution block). Parameters use the API's names.

    /**
     * GET /api/v1/supplemental-withholding (API 1.8): tax withheld on a bonus, commission, RSU vest, option exercise
     * or lump sum paid on top of $salary, next to the final tax the payment adds.
     *
     * @param array<string, scalar|null> $params `type` (bonus, commission, rsu_vest, option_exercise, lump_sum),
     *        `region` (required for `us` and `ca`) and the country's own options.
     * @return array<string, mixed>
     */
    public function supplementalWithholding(string $country, int|float $salary, int|float $amount, array $params = []): array
    {
        return $this->json('supplemental-withholding', ['country' => $country, 'salary' => $salary, 'amount' => $amount] + $params);
    }

    /**
     * GET /api/v1/equity-tax (API 1.8): tax on RSUs and stock options at vest or exercise, and on sale.
     *
     * @param string $instrument us: rsu, nso, iso; uk: rsu, emi, csop, unapproved_option; au: ess_upfront,
     *        ess_deferred, rsu; ca: stock_option; in: esop, rsu.
     * @param array<string, scalar|null> $params `fmv_event`, `fmv_grant`, `exercise_price`, `sale_date`,
     *        `sale_price`, `salary`, `region` and the country's options.
     * @return array<string, mixed>
     */
    public function equityTax(string $country, string $instrument, int|float $units, string $grantDate, string $eventDate, array $params = []): array
    {
        return $this->json('equity-tax', ['country' => $country, 'instrument' => $instrument, 'units' => $units, 'grant_date' => $grantDate, 'event_date' => $eventDate] + $params);
    }

    /**
     * GET /api/v1/cross-border-equity (API 1.9): an RSU vest or option exercise split between the countries worked
     * in from grant to vest, with each share's estimated tax.
     *
     * @param string $periods Comma-separated country:start:end[:workdays], e.g.
     *        "us:2024-01-01:2024-12-31:220,gb:2025-01-01:2025-06-30:120".
     * @param array<string, scalar|null> $params `vest_date`, `fmv_event`, `exercise_price`, `currency`,
     *        `residence`, `grant_country`, `us_citizen`, `salaries` ("us:150000,gb:80000"), `options`.
     * @return array<string, mixed>
     */
    public function crossBorderEquity(string $instrument, int|float $units, string $grantDate, string $eventDate, string $periods, array $params = []): array
    {
        return $this->json('cross-border-equity', ['instrument' => $instrument, 'units' => $units, 'grant_date' => $grantDate, 'event_date' => $eventDate, 'periods' => $periods] + $params);
    }

    /**
     * GET /api/v1/facts (API 1.9): tax facts by country, tax type, jurisdiction and year, paged with `cursor`.
     *
     * @param array<string, scalar|null> $params `country`, `tax_type` (income.pit, consumption.vat...), `level`,
     *        `jurisdiction` (gb-sct, us-ca), `year`, `limit`, `cursor`.
     * @return array<string, mixed>
     */
    public function facts(array $params = []): array
    {
        return $this->json('facts', $params);
    }

    /**
     * GET /api/v1/tax-calendar (API 1.9): tax year, next tax year, enacted and announced changes and the next
     * budget date. Without a country, every country's calendar.
     *
     * @return array<string, mixed>
     */
    public function taxCalendar(?string $country = null, ?string $region = null): array
    {
        return $this->json('tax-calendar', ['country' => $country, 'region' => $region]);
    }

    /**
     * GET /api/v1/tariffs (API 1.9): tariff lines of a commodity code in the UK (gb), EU (eu or a member state),
     * US (us) or Indian (in) tariff.
     *
     * @param array<string, scalar|null> $params `origin` (the best preference for that origin), `limit`, `cursor`.
     * @return array<string, mixed>
     */
    public function tariffs(string $destination, string $code, array $params = []): array
    {
        return $this->json('tariffs', ['destination' => $destination, 'code' => $code] + $params);
    }

    /**
     * GET /api/v1/trade-agreements (API 1.9): trade agreements and preference schemes in a country's tariff.
     *
     * @return array<string, mixed>
     */
    public function tradeAgreements(string $country, ?string $partner = null): array
    {
        return $this->json('trade-agreements', ['country' => $country, 'partner' => $partner]);
    }

    /**
     * GET /api/v1/import-duty (API 1.9): duties, import VAT, excise and landed cost of one import. An estimate, not
     * a customs ruling.
     *
     * @param array<string, scalar|null> $params `quantity`, `unit`, `proof_of_origin`, `additional_code`,
     *        `excise_code`, `documents`, `vat_code`, `transport` (US).
     * @return array<string, mixed>
     */
    public function importDuty(string $destination, string $origin, string $code, int|float $customsValue, array $params = []): array
    {
        return $this->json('import-duty', ['destination' => $destination, 'origin' => $origin, 'code' => $code, 'customs_value' => $customsValue] + $params);
    }

    /**
     * The X-RateLimit-* values of the last response, or null before the first request.
     */
    public function lastRateLimit(): ?RateLimit
    {
        return $this->lastRateLimit;
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    private function json(string $path, array $query = []): array
    {
        $response = $this->request($path, $query, 'application/json');
        /** @var array<string, mixed> $data */
        $data = Json::decode($response->body, 'The API response');

        return $data;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function request(string $path, array $query, string $accept): Response
    {
        if (array_key_exists('key', $query)) {
            throw new \InvalidArgumentException('Pass the API key to the Client constructor; the API refuses keys in the URL.');
        }
        $pairs = [];
        foreach ($query as $name => $value) {
            if ($value === null) {
                continue;
            }
            $pairs[$name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        $url = $this->baseUrl . '/api/v1/' . $path . ($pairs !== [] ? '?' . http_build_query($pairs, '', '&', PHP_QUERY_RFC3986) : '');
        $headers = [
            'Accept' => $accept,
            'User-Agent' => 'calcbloc-php/' . self::VERSION . ' (+https://calcbloc.com/developers)',
        ];
        if ($this->apiKey !== null) {
            $headers['X-API-Key'] = $this->apiKey;
        }

        $response = $this->transport->get($url, $headers, $this->timeout);
        $rateLimit = RateLimit::fromHeaders($response->headers);
        if ($rateLimit !== null) {
            $this->lastRateLimit = $rateLimit;
        }
        $limit = $response->header('x-ratelimit-limit');
        if ($limit !== null && ctype_digit(trim($limit)) && (int) $limit > 0) {
            $this->dailyLimit = (int) $limit;
        }
        if ($response->status >= 200 && $response->status < 300) {
            return $response;
        }

        throw $this->error($response, $rateLimit);
    }

    private function error(Response $response, ?RateLimit $rateLimit): CalcBlocException
    {
        $code = 'http_error';
        $message = sprintf('The CalcBloc API answered HTTP %d.', $response->status);
        $docs = null;
        $body = json_decode($response->body, true);
        if (is_array($body) && is_array($body['error'] ?? null)) {
            $error = $body['error'];
            $code = is_string($error['code'] ?? null) ? $error['code'] : $code;
            $message = is_string($error['message'] ?? null) ? $error['message'] : $message;
            $docs = is_string($error['docs'] ?? null) ? $error['docs'] : null;
        }
        if ($response->status === 429 || $code === 'rate_limited') {
            $retry = $response->header('retry-after');
            $retryAfter = $retry !== null && is_numeric($retry) ? (int) $retry : null;
            if ($this->apiKey === null) {
                $message = self::noKeyLimitMessage($message, $retryAfter, $this->dailyLimit);
            }

            return new RateLimitException($message, $docs, $retryAfter, $rateLimit, $response->headers);
        }

        return new CalcBlocException($message, $code, $response->status, $docs, null, $response->headers);
    }

    /** True when an API key is configured (passed in, or from CALCBLOC_API_KEY). */
    public function hasApiKey(): bool
    {
        return $this->apiKey !== null;
    }

    private static function envApiKey(): ?string
    {
        $value = getenv(self::API_KEY_ENV);
        if (!is_string($value) || $value === '') {
            $value = $_ENV[self::API_KEY_ENV] ?? $_SERVER[self::API_KEY_ENV] ?? null;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * The 429 message without a key: the free allowance is used up; add a free key and how to set it. No limit is
     * typed in here: the daily figure is the API's X-RateLimit-Limit header, quoted once a response has carried it.
     */
    private static function noKeyLimitMessage(string $apiMessage, ?int $retryAfter, ?int $dailyLimit): string
    {
        $perMinute = stripos($apiMessage, 'minute') !== false && ($retryAfter === null || $retryAfter <= 60);
        $perDay = $dailyLimit !== null ? ' (' . number_format($dailyLimit) . ' requests a day)' : '';
        $usedUp = $perMinute
            ? 'The free per-minute allowance without a key is used up for this minute.'
            : 'The free daily allowance without a key' . $perDay . ' is used up.';

        return $usedUp . ' Add your free API key (email only, much higher limits): ' . self::KEYS_URL
            . ' (set it with new Client(apiKey: \'...\') or the ' . self::API_KEY_ENV . ' environment variable).';
    }
}
