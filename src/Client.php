<?php

declare(strict_types=1);

namespace CalcBloc;

use CalcBloc\Http\CurlTransport;
use CalcBloc\Http\Response;
use CalcBloc\Http\StreamTransport;
use CalcBloc\Http\Transport;
use CalcBloc\Internal\Json;

/**
 * Client for the CalcBloc API (https://calcbloc.com/developers).
 *
 * Every endpoint works without a key for 50 requests a day (5 a minute) per IP address. A free API key, sent in
 * the X-API-Key header, raises that to 5,000 a day (60 a minute). The key comes from the constructor or, when none
 * is passed, from the CALCBLOC_API_KEY environment variable. When the free allowance without a key is used up,
 * RateLimitException says so and links to the keys page; see https://calcbloc.com/developers#limits.
 *
 *     $client = new CalcBloc\Client();                    // no key, or CALCBLOC_API_KEY from the environment
 *     $client = new CalcBloc\Client(apiKey: 'cb_...');    // a free key: 5,000 requests a day
 *     $table = $client->taxTables('canada', 'latest', region: 'on');
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

    /**
     * @param string|null $apiKey Your API key (free at https://calcbloc.com/developers/keys). Null reads the
     *                            CALCBLOC_API_KEY environment variable; '' (or neither set) calls without a key,
     *                            which allows 50 requests a day.
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
     * @param string $country Country code, e.g. `us`, `germany`, `new-zealand`.
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
        $path = 'tax-tables/' . rawurlencode(strtolower(trim($country))) . '/' . rawurlencode(trim($year));
        $query = ['region' => $region !== null && trim($region) !== '' ? trim($region) : null];
        if ($format === 'csv') {
            $query['format'] = 'csv';

            return $this->request($path, $query, 'text/csv')->body;
        }

        return TaxTable::fromArray($this->json($path, $query));
    }

    /**
     * GET /api/v1/net-salary: take-home pay for an annual salary (needs an API key).
     *
     * @param string $country Country code with a calculation engine (see ApiCountry::$tablesOnly).
     * @param int|float $salary Annual gross salary in the country's currency (India: annual CTC).
     * @param array<string, scalar|null> $params Other query parameters: `year`, `region` and the country's own
     *        parameters as listed by countries() (e.g. `filing_status` for the US).
     */
    public function netSalary(string $country, int|float $salary, array $params = []): CalculationResult
    {
        return CalculationResult::fromArray($this->json('net-salary', ['country' => $country, 'salary' => $salary] + $params));
    }

    /**
     * GET /api/v1/employer-cost: total cost of employing someone at an annual salary (needs an API key).
     *
     * @param array<string, scalar|null> $params Other query parameters, as for netSalary().
     */
    public function employerCost(string $country, int|float $salary, array $params = []): CalculationResult
    {
        return CalculationResult::fromArray($this->json('employer-cost', ['country' => $country, 'salary' => $salary] + $params));
    }

    /**
     * GET /api/v1/compare: the same salary in two countries (needs an API key).
     *
     * @param array<string, scalar|null> $params `currency` (a's or b's), `us_state`, `uk_region`, `ca_province`, `in_state`.
     */
    public function compare(string $a, string $b, int|float $salary, array $params = []): ComparisonResult
    {
        return ComparisonResult::fromArray($this->json('compare', ['a' => $a, 'b' => $b, 'salary' => $salary] + $params));
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
                $message = self::noKeyLimitMessage($message, $retryAfter);
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

    /** The 429 message without a key: the free allowance is used up; add a free key and how to set it. */
    private static function noKeyLimitMessage(string $apiMessage, ?int $retryAfter): string
    {
        $perMinute = stripos($apiMessage, 'minute') !== false && ($retryAfter === null || $retryAfter <= 60);
        $usedUp = $perMinute
            ? 'The free allowance without a key (5 requests a minute, 50 a day) is used up for this minute.'
            : 'The free allowance of 50 requests a day without a key is used up.';

        return $usedUp . ' Add your free API key (5,000 requests a day): ' . self::KEYS_URL
            . ' (set it with new Client(apiKey: \'...\') or the ' . self::API_KEY_ENV . ' environment variable).';
    }
}
