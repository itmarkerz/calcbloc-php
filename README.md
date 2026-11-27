# CalcBloc PHP: tax tables 2026 API client with income tax brackets and social security rates

<!-- Badges: enable once the package is on Packagist and the repository is public.
[![Tests](https://github.com/itmarkerz/calcbloc-php/actions/workflows/tests.yml/badge.svg)](https://github.com/itmarkerz/calcbloc-php/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/calcbloc/calcbloc.svg)](https://packagist.org/packages/calcbloc/calcbloc)
[![PHP version](https://img.shields.io/packagist/dependency-v/calcbloc/calcbloc/php.svg)](https://packagist.org/packages/calcbloc/calcbloc)
[![License: MIT](https://img.shields.io/badge/code-MIT-blue.svg)](LICENSE)
[![Data: CC BY 4.0](https://img.shields.io/badge/data-CC%20BY%204.0-green.svg)](https://calcbloc.com/data?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss)
-->

`calcbloc/calcbloc` is the PHP package for [CalcBloc](https://calcbloc.com/?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) tax data. It does two things:

- **Offline tax tables.** Official **income tax brackets**, **social security rates**, payroll thresholds and employer contributions for the countries listed [below](#coverage), bundled as JSON. No network and no API key. Every figure carries the official source it comes from and the date it was last checked.
- **API client.** A typed client for the [CalcBloc API](https://calcbloc.com/developers?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss): tax tables for every country, year and region, plus net salary, employer cost and two-country salary comparison from the same engines as the calculators on calcbloc.com. API 1.8 and 1.9 add bonus/supplemental withholding, RSU and stock option tax (also split across countries), tax facts, tax calendars, tariffs, trade agreements and import duty; API 1.10 adds double taxation treaties (withholding rate limits, as published) and take-home pay and employer cost for Denmark, Estonia and Poland. Cross-border salary withholding (multi-country payroll) and the double-tax relief amount are not supported yet.

PHP 8.1 or later, no required dependencies.

CalcBloc is a product of [itmarkerz technologies](https://itmarkerz.co.in/?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss).

## Installation

```bash
composer require calcbloc/calcbloc
```

> **Not yet published.** The package is not on Packagist yet, so the command above does not work today. Until it is, add this repository as a [VCS repository](https://getcomposer.org/doc/05-repositories.md#vcs) in your `composer.json`.

## Examples

The three examples below use only the bundled data. Their scripts are in [`examples/`](examples), and the output shown is what they print with the data in this release.

### 1. US federal income tax brackets 2026

```php
use CalcBloc\Tables;

$single = Tables::load('us', '2026')->section('federal-income-tax-single');

foreach ($single->rows as $band) {
    printf("%-22s %5.1f%%\n",
        '$' . number_format($band['from']) . ($band['to'] === null ? ' and over' : ' to $' . number_format($band['to'])),
        $band['rate'] * 100);
}
printf("Tax on $85,000 taxable income: $%s\n", number_format(Tables::taxFromBands($single, 85000), 2));
printf("Source: %s (checked %s)\n", $single->source->url, $single->source->retrievedAt);
```

```text
$0 to $12,400           10.0%
$12,400 to $50,400      12.0%
$50,400 to $105,700     22.0%
$105,700 to $201,775    24.0%
$201,775 to $256,225    32.0%
$256,225 to $640,600    35.0%
$640,600 and over       37.0%
Tax on $85,000 taxable income: $13,412.00
Source: https://www.irs.gov/pub/irs-drop/rp-25-32.pdf (checked 2026-10-20)
```

### 2. Social security rates: Germany 2026

```php
use CalcBloc\Tables;

$table = Tables::load('germany');

foreach ($table->sectionsInGroup('employee') as $section) {
    echo $section->title, "\n";
    foreach ($section->rows as $row) {
        $value = match ($row['unit']) {
            'rate' => rtrim(rtrim(number_format($row['value'] * 100, 2), '0'), '.') . '%',
            'money' => number_format($row['value'], 2) . ' ' . $table->currency,
            default => (string) $row['value'],
        };
        printf("  %s: %s\n", $row['label'], $value);
    }
}
```

```text
Pension insurance (Rentenversicherung)
  Contribution rate (employee and employer each pay half): 18.6%
  Contribution ceiling (Beitragsbemessungsgrenze), per year: 101,400.00 EUR
  Contribution ceiling, per month: 8,450.00 EUR
Unemployment insurance (Arbeitslosenversicherung)
  Contribution rate (employee and employer each pay half): 2.6%
  Contribution ceiling, per year (same as pension insurance): 101,400.00 EUR
Health insurance (Krankenversicherung)
  General contribution rate (employee and employer each pay half): 14.6%
  Average additional contribution rate (Zusatzbeitrag; each fund sets its own, shared equally): 2.9%
  Contribution ceiling, per year: 69,750.00 EUR
  Contribution ceiling, per month: 5,812.50 EUR
  Compulsory insurance threshold (Versicherungspflichtgrenze), per year: 77,400.00 EUR
Long-term care insurance (Pflegeversicherung)
  Contribution rate (employee and employer each pay half; see Saxony below): 3.6%
  Surcharge for childless members (employee only): 0.6%
  Childless surcharge from age: 23
  Reduction per child (employee share): 0.25%
  Children counted for the reduction: 2nd to 5th child under 25
  Saxony: extra employee share (employer pays correspondingly less): 1%
  Contribution ceiling, per year (same as health insurance): 69,750.00 EUR
```

### 3. Regional tables: Canada federal and Ontario brackets

```php
use CalcBloc\Tables;

$federal = Tables::load('canada', '2026')->section('federal-income-tax');
$ontario = Tables::load('canada', '2026', 'on')->section('provincial-income-tax'); // region code or slug

printf("Federal brackets: CAD %s\n", number_format($federal->taxOn(90000), 2));
printf("Ontario brackets: CAD %s\n", number_format($ontario->taxOn(90000), 2));
```

```text
Federal brackets: CAD 14,646.01
Ontario brackets: CAD 6,025.47
```

These are the amounts from the brackets alone, before non-refundable credits, the Ontario surtax and the Ontario Health Premium. Each of those is a separate section of the same tables.

## Offline tables

```php
use CalcBloc\Tables;

Tables::listCountries();                    // list<CountryInfo>: code, name, currency, years, regions, pageUrl, apiUrl, lastVerified
Tables::load('uk');                         // current tax year (same as 'latest')
Tables::load('nz');                         // short code, ISO code ('NZ'), English name or key ('new-zealand')
Tables::load('uk', '2025-26');              // an earlier year, where bundled
Tables::load('uk', null, 'scotland');       // a region: US state, Canadian province, Scotland or Wales, and more
Tables::manifest();                         // the raw data/manifest.json
```

`Tables::load()` returns a `TaxTable`:

| Property / method | What it holds |
| --- | --- |
| `country`, `countryName`, `year`, `currency`, `region` | Which table this is. |
| `effectiveFrom`, `effectiveTo`, `status`, `underReview` | When the figures apply and whether any section is under review. |
| `sections` | `list<Section>`: each has `id`, `title`, `group` (`income-tax`, `employee`, `employer`, `other`), `kind` (`bands` or `values`), `rows`, `basis`, `note` and its `source` (title, URL, check date). |
| `section($id)`, `sectionsInGroup($group)` | Lookups. |
| `rows` | One flat row per band or value, each with `source_url` and `retrieved_at` (the same rows as the CSV download). |
| `sources`, `pageUrl`, `attribution` | Every official source, the table's page on calcbloc.com, and the credit line. |
| `toArray()` | The table in the exact shape of the API response. |

Band rows hold `from`, `to` (`null` = no upper limit) and `rate` as a decimal (`0.2` = 20%). Value rows hold `label`, `value` and `unit` (`rate`, `money`, `number` or `text`).

### `taxFromBands()`

`Tables::taxFromBands($section, $income)` (or `$section->taxOn($income)`) adds up the tax on each slice of income between a band's `from` and `to`. Pass income in the section's `basis`, which is usually taxable income per year. It applies no allowances, credits, reliefs, surcharges or rounding rules. For UK bands, for example, the basis is taxable income above the Personal Allowance. It is a convenience for working with the published brackets, not tax advice. For full take-home pay, use the `net-salary` endpoint.

Errors use the API's error codes: `Tables::load('atlantis')` throws a `CalcBlocException` with `getErrorCode() === 'unsupported_country'`. An unknown year gives `unsupported_year`, and an unknown region gives `unsupported_region`.

## API client

```php
use CalcBloc\Client;

$client = new Client(); // no key (free allowance per IP address), or CALCBLOC_API_KEY; new Client(apiKey: 'cb_...') to pass one

$countries = $client->countries();                              // list<ApiCountry>: years, regions, endpoints and their parameters
$table     = $client->taxTables('canada', 'latest', 'on');      // TaxTable (same object as Tables::load())
$csv       = $client->taxTables('us', '2026', 'ca', 'csv');     // CSV text, one row per band or value

$pay  = $client->netSalary('uk', 50000, ['region' => 'scotland']);    // CalculationResult
$pay->summary['netAnnual'];                                            // plus results, breakdown, notes, calculatorUrl
$cost = $client->employerCost('germany', 65000);                      // CalculationResult: summary['totalCost'], ...
$both = $client->compare('uk', 'ireland', 60000, ['currency' => 'GBP']); // ComparisonResult
$us   = $client->netSalary('us', 90000, ['region' => 'ca', 'filing_status' => 'mfj']); // short codes: 'nz', 'de', 'ae', ...

// API 1.8 and 1.9: these return the API's JSON as an array (fields documented at calcbloc.com/developers)
$bonus = $client->supplementalWithholding('us', 150000, 20000, ['type' => 'bonus', 'region' => 'ca']);
$emi   = $client->equityTax('uk', 'emi', 1000, '2024-01-15', '2026-03-01', ['fmv_event' => 12, 'exercise_price' => 2]);
$split = $client->crossBorderEquity('rsu', 500, '2024-01-01', '2026-01-01', 'us:2024-01-01:2024-12-31,gb:2025-01-01:2026-01-01', ['fmv_event' => 80]);
$facts = $client->facts(['country' => 'uk', 'tax_type' => 'income.pit']);
$cal   = $client->taxCalendar('au');                         // null: every country
$lines = $client->tariffs('gb', '0901', ['origin' => 'in']);   // UK, EU (or a member state), US, India
$deals = $client->tradeAgreements('gb', 'in');
$duty  = $client->importDuty('gb', 'in', '0901210000', 1000, ['proof_of_origin' => true]); // an estimate, not a customs ruling

// API 1.10: tax treaties (an array: country, partner, count, treaties); Denmark, Estonia and Poland
$treaties = $client->taxTreaties('in', 'us');               // treaties are stored for India
$dk = $client->netSalary('dk', 600000, ['municipality' => 'average', 'church' => false, 'atp_hours' => '0']);
$ee = $client->netSalary('ee', 30000, ['funded_pension' => '0', 'pension_age' => false]);
$pl = $client->employerCost('pl', 120000, ['accident_rate_pct' => 1.67, 'commuter' => false]);
$pl->verification;                                          // 'official-example', 'law-text' or null
```

API calls accept a country by its short code (ISO 3166-1 alpha-2 in lower case, with `uk` for the United Kingdom; `gb` also works), such as `nz`, `de` or `ae`, as well as its English name or the older key (`new-zealand`, `germany`). Responses still carry the older key in `country`. The offline `Tables` accept the same short codes, ISO codes (`NZ`) and English names (`New Zealand`), case-insensitively, next to the keys in the [coverage](#coverage) table.

`region` is required for take-home pay in `us` and `ca`, and for employer cost in `us`, `au` and `ca`; without it the API answers HTTP 400 `missing_parameter` with the valid codes. The US `filing_status` values are `single`, `mfj` and `hoh` (`married` and `married_joint` are accepted for `mfj`, `head_of_household` for `hoh`).

Each country's parameters for `net-salary` and `employer-cost` (for example `filing_status` in the US or `tax_class` in Germany) are listed by `countries()` and in the [OpenAPI description](https://calcbloc.com/api/openapi.json). Countries marked `tablesOnly` have tax tables but no calculation engine yet. The calculation endpoints answer them with HTTP 400 `unsupported_country`.

Since API 1.10, `CalculationResult::$verification` says how a calculation is checked: `official-example` where the golden tests reproduce a worked example published by the tax authority or social security institution (Estonia, Poland), `law-text` where no official example is published and the tests are worked by hand from the law (Denmark), and `null` for the countries with their own engine. With a value, the first note says how the calculation was checked and links the official sources. `ApiCountry::verification('net-salary')` gives the same per country.

`taxTreaties()` (API 1.10) lists, for each treaty, the maximum withholding rates in the source State on dividends, interest, royalties and fees for technical services (decimals, 0.15 = 15%) with their conditions, articles and wording, the relief method of each country, signature and entry-into-force dates, MLI status and the official source. Nothing is computed.

### Free use and API keys

- **Offline tables need no key and no network**: `Tables` reads the JSON bundled in the package.
- **API calls work without a key** for 300 requests a day and 20 a minute per IP address (see [calcbloc.com/developers#limits](https://calcbloc.com/developers?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss#limits)), on every endpoint.
- **Add a free API key for 5,000 requests a day** (60 a minute). Get one instantly at [calcbloc.com/developers/keys](https://calcbloc.com/developers/keys?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) (enter your email address; the key is shown once).
- **Set it** with `new Client(apiKey: 'cb_...')`, or in the `CALCBLOC_API_KEY` environment variable, which `new Client()` reads when no key is passed (pass `''` to call without a key even when the variable is set). The client sends it in the `X-API-Key` header. Keys in the URL are refused.

| Access | Per day | Per minute |
| --- | --- | --- |
| No key (per IP address) | 300 | 20 |
| Free key | 5,000 | 60 |

When the free allowance without a key is used up, the client throws a `RateLimitException` whose message says so (with the daily limit from the API's `X-RateLimit-Limit` header once a response has carried it), links to the keys page and shows how to set the key. With a key, the message gives that key's limit.

Days reset at midnight UTC. `$client->lastRateLimit()` returns the `X-RateLimit-*` values of the last response (`limit` is the daily limit). Current limits and commercial terms are on the [developer page](https://calcbloc.com/developers?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss#limits). Responses can be cached for a day, so cache on your side, or use the offline tables when the bundled year is the one you need.

### Errors

Every API error is a `CalcBloc\CalcBlocException` built from the API's error body `{"error": {"code", "message", "docs"}}`:

```php
use CalcBloc\CalcBlocException;
use CalcBloc\RateLimitException;

try {
    $client->netSalary('norway', 700000);
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter() ?? 60);           // HTTP 429 rate_limited; without a key the message asks for a free key
} catch (CalcBlocException $e) {
    $e->getErrorCode();  // "unsupported_country" (Norway has tax tables only), "invalid_key", "invalid_parameter", ...
    $e->getStatus();     // 400 (also getCode())
    $e->getDocs();       // link to the relevant documentation
}
```

A request that gets no HTTP response at all (DNS, timeout, TLS) throws `CalcBloc\TransportException` (`transport_error`).

### HTTP transport

The client uses ext-curl when it is loaded and PHP streams otherwise. To use your own HTTP stack, implement `CalcBloc\Http\Transport` (one `get()` method), or wrap any PSR-18 client:

```php
use CalcBloc\Client;
use CalcBloc\Http\Psr18Transport;

$client = new Client($apiKey, transport: new Psr18Transport($psr18Client, $psr17RequestFactory));
```

## Offline data or the API?

| | Bundled tables (`Tables`) | API (`Client`) |
| --- | --- | --- |
| Network, key | None | HTTPS; 300 requests a day and 20 a minute per IP address without a key (see [calcbloc.com/developers#limits](https://calcbloc.com/developers?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss#limits)), 5,000 a day with a free key |
| Content | Tax tables as of this release | Always the current published tables |
| Calculations | `taxFromBands()` on one set of brackets | Full net salary, employer cost and comparison engines |
| Limits | None | See [free use and API keys](#free-use-and-api-keys) |

Tax figures change: budgets, mid-year changes and corrections. The tables are dated (`lastVerified`, and `retrieved_at` on every row). Update the package regularly, or call the API when you need the latest figures.

## Coverage

Generated from `data/manifest.json` by `composer coverage-table`. "Regions" counts the regional tables bundled (US states and DC, Canadian provinces and territories, UK nations, Spanish autonomous communities, Swiss cantons and so on).

<!-- coverage:start -->
| Country | Code | Currency | Tax years | Regions | Last checked | API endpoints |
| --- | --- | --- | --- | --- | --- | --- |
| [Albania](https://calcbloc.com/albania/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `albania` | ALL | 2026 | - | 2026-11-26 | tax tables |
| [Algeria](https://calcbloc.com/algeria/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `algeria` | DZD | 2026 | - | 2026-11-26 | tax tables |
| [American Samoa](https://calcbloc.com/american-samoa/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `american-samoa` | USD | 2026 | - | 2026-11-27 | tax tables |
| [Andorra](https://calcbloc.com/andorra/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `andorra` | EUR | 2026 | - | 2026-11-26 | tax tables |
| [Angola](https://calcbloc.com/angola/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `angola` | AOA | 2026 | - | 2026-11-27 | tax tables |
| [Anguilla](https://calcbloc.com/anguilla/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `anguilla` | XCD | 2026 | - | 2026-11-27 | tax tables |
| [Antigua and Barbuda](https://calcbloc.com/antigua-and-barbuda/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `antigua-and-barbuda` | XCD | 2026 | - | 2026-11-26 | tax tables |
| [Argentina](https://calcbloc.com/argentina/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `argentina` | ARS | 2026 | - | 2026-11-26 | tax tables |
| [Armenia](https://calcbloc.com/armenia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `armenia` | AMD | 2026 | - | 2026-11-27 | tax tables |
| [Aruba](https://calcbloc.com/aruba/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `aruba` | AWG | 2026 | - | 2026-11-27 | tax tables |
| [Australia](https://calcbloc.com/australia/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `australia` | AUD | 2026-27 | - | 2026-11-27 | tax tables, net-salary, employer-cost, compare |
| [Austria](https://calcbloc.com/austria/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `austria` | EUR | 2026 | - | 2026-11-27 | tax tables |
| [Azerbaijan](https://calcbloc.com/azerbaijan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `azerbaijan` | AZN | 2026 | - | 2026-11-26 | tax tables |
| [Bahamas](https://calcbloc.com/bahamas/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `bahamas` | BSD | 2026 | - | 2026-11-26 | tax tables |
| [Bahrain](https://calcbloc.com/bahrain/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `bahrain` | BHD | 2026 | - | 2026-11-22 | tax tables |
| [Bangladesh](https://calcbloc.com/bangladesh/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `bangladesh` | BDT | 2026-27 | - | 2026-11-22 | tax tables |
| [Barbados](https://calcbloc.com/barbados/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `barbados` | BBD | 2026 | - | 2026-11-26 | tax tables |
| [Belarus](https://calcbloc.com/belarus/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `belarus` | BYN | 2026 | - | 2026-11-26 | tax tables |
| [Belize](https://calcbloc.com/belize/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `belize` | BZD | 2026 | - | 2026-11-26 | tax tables |
| [Benin](https://calcbloc.com/benin/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `benin` | XOF | 2026 | - | 2026-11-26 | tax tables |
| [Bermuda](https://calcbloc.com/bermuda/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `bermuda` | BMD | 2026-27 | - | 2026-11-26 | tax tables |
| [Bhutan](https://calcbloc.com/bhutan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `bhutan` | BTN | 2026 | - | 2026-11-27 | tax tables |
| [Bolivia](https://calcbloc.com/bolivia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `bolivia` | BOB | 2026 | - | 2026-11-26 | tax tables |
| [Bosnia and Herzegovina](https://calcbloc.com/bosnia-and-herzegovina/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `bosnia-and-herzegovina` | BAM | 2026 | - | 2026-11-26 | tax tables |
| [Botswana](https://calcbloc.com/botswana/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `botswana` | BWP | 2026-27 | - | 2026-11-26 | tax tables |
| [Brazil](https://calcbloc.com/brazil/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `brazil` | BRL | 2026 | - | 2026-11-27 | tax tables |
| [British Virgin Islands](https://calcbloc.com/british-virgin-islands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `british-virgin-islands` | USD | 2026 | - | 2026-11-26 | tax tables |
| [Brunei](https://calcbloc.com/brunei/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `brunei` | BND | 2026 | - | 2026-11-26 | tax tables |
| [Bulgaria](https://calcbloc.com/bulgaria/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `bulgaria` | EUR | 2026 | - | 2026-11-27 | tax tables |
| [Burkina Faso](https://calcbloc.com/burkina-faso/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `burkina-faso` | XOF | 2026 | - | 2026-11-26 | tax tables |
| [Burundi](https://calcbloc.com/burundi/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `burundi` | BIF | 2026-27 | - | 2026-11-27 | tax tables |
| [Cabo Verde](https://calcbloc.com/cabo-verde/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `cabo-verde` | CVE | 2026 | - | 2026-11-26 | tax tables |
| [Cambodia](https://calcbloc.com/cambodia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `cambodia` | KHR | 2026 | - | 2026-11-26 | tax tables |
| [Cameroon](https://calcbloc.com/cameroon/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `cameroon` | XAF | 2026 | - | 2026-11-26 | tax tables |
| [Canada](https://calcbloc.com/canada/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `canada` | CAD | 2026 | 13 | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [Caribbean Netherlands](https://calcbloc.com/caribbean-netherlands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `caribbean-netherlands` | USD | 2026 | - | 2026-11-27 | tax tables |
| [Cayman Islands](https://calcbloc.com/cayman-islands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `cayman-islands` | KYD | 2026 | - | 2026-11-26 | tax tables |
| [Central African Republic](https://calcbloc.com/central-african-republic/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `central-african-republic` | XAF | 2026 | - | 2026-11-27 | tax tables |
| [Chad](https://calcbloc.com/chad/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `chad` | XAF | 2026 | - | 2026-11-26 | tax tables |
| [Chile](https://calcbloc.com/chile/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `chile` | CLP | 2026 | - | 2026-11-22 | tax tables |
| [China](https://calcbloc.com/china/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `china` | CNY | 2026 | - | 2026-11-22 | tax tables |
| [Colombia](https://calcbloc.com/colombia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `colombia` | COP | 2026 | - | 2026-11-22 | tax tables |
| [Comoros](https://calcbloc.com/comoros/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `comoros` | KMF | 2026 | - | 2026-11-27 | tax tables |
| [Cook Islands](https://calcbloc.com/cook-islands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `cook-islands` | NZD | 2026 | - | 2026-11-27 | tax tables |
| [Costa Rica](https://calcbloc.com/costa-rica/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `costa-rica` | CRC | 2026 | - | 2026-11-22 | tax tables |
| [Croatia](https://calcbloc.com/croatia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `croatia` | EUR | 2026 | 21 | 2026-11-22 | tax tables |
| [Cuba](https://calcbloc.com/cuba/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `cuba` | CUP | 2026 | - | 2026-11-26 | tax tables |
| [Curaçao](https://calcbloc.com/curacao/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `curacao` | XCG | 2026 | - | 2026-11-27 | tax tables |
| [Cyprus](https://calcbloc.com/cyprus/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `cyprus` | EUR | 2026 | - | 2026-11-22 | tax tables |
| [Czechia](https://calcbloc.com/czechia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `czechia` | CZK | 2026 | - | 2026-11-22 | tax tables |
| [Côte d’Ivoire](https://calcbloc.com/cote-divoire/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `cote-divoire` | XOF | 2026 | - | 2026-11-26 | tax tables |
| [DR Congo](https://calcbloc.com/dr-congo/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `dr-congo` | CDF | 2026 | - | 2026-11-26 | tax tables |
| [Denmark](https://calcbloc.com/denmark/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `denmark` | DKK | 2026 | 5 | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [Djibouti](https://calcbloc.com/djibouti/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `djibouti` | DJF | 2026 | - | 2026-11-26 | tax tables |
| [Dominica](https://calcbloc.com/dominica/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `dominica` | XCD | 2026 | - | 2026-11-26 | tax tables |
| [Dominican Republic](https://calcbloc.com/dominican-republic/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `dominican-republic` | DOP | 2026 | - | 2026-11-26 | tax tables |
| [Ecuador](https://calcbloc.com/ecuador/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `ecuador` | USD | 2026 | - | 2026-11-26 | tax tables |
| [Egypt](https://calcbloc.com/egypt/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `egypt` | EGP | 2026 | - | 2026-11-26 | tax tables |
| [El Salvador](https://calcbloc.com/el-salvador/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `el-salvador` | USD | 2026 | - | 2026-11-26 | tax tables |
| [Estonia](https://calcbloc.com/estonia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `estonia` | EUR | 2026 | - | 2026-11-21 | tax tables, net-salary, employer-cost, compare |
| [Eswatini](https://calcbloc.com/eswatini/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `eswatini` | SZL | 2026-27 | - | 2026-11-26 | tax tables |
| [Ethiopia](https://calcbloc.com/ethiopia/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `ethiopia` | ETB | 2026-27 | - | 2026-11-26 | tax tables |
| [Falkland Islands](https://calcbloc.com/falkland-islands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `falkland-islands` | FKP | 2026 | - | 2026-11-27 | tax tables |
| [Faroe Islands](https://calcbloc.com/faroe-islands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `faroe-islands` | DKK | 2026 | - | 2026-11-27 | tax tables |
| [Fiji](https://calcbloc.com/fiji/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `fiji` | FJD | 2026 | - | 2026-11-26 | tax tables |
| [Finland](https://calcbloc.com/finland/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `finland` | EUR | 2026 | 19 | 2026-11-22 | tax tables |
| [France](https://calcbloc.com/france/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `france` | EUR | 2026 | - | 2026-11-27 | tax tables, net-salary, compare |
| [French Polynesia](https://calcbloc.com/french-polynesia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `french-polynesia` | XPF | 2026 | - | 2026-11-27 | tax tables |
| [French Southern Territories](https://calcbloc.com/french-southern-territories/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `french-southern-territories` | EUR | 2026 | - | 2026-11-27 | tax tables |
| [Gabon](https://calcbloc.com/gabon/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `gabon` | XAF | 2026 | - | 2026-11-26 | tax tables |
| [Georgia](https://calcbloc.com/georgia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `georgia` | GEL | 2026 | - | 2026-11-26 | tax tables |
| [Germany](https://calcbloc.com/germany/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `germany` | EUR | 2026 | - | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [Ghana](https://calcbloc.com/ghana/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `ghana` | GHS | 2026 | - | 2026-11-26 | tax tables |
| [Gibraltar](https://calcbloc.com/gibraltar/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `gibraltar` | GIP | 2026-27 | - | 2026-11-27 | tax tables |
| [Greece](https://calcbloc.com/greece/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `greece` | EUR | 2026 | - | 2026-11-22 | tax tables |
| [Greenland](https://calcbloc.com/greenland/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `greenland` | DKK | 2026 | - | 2026-11-27 | tax tables |
| [Grenada](https://calcbloc.com/grenada/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `grenada` | XCD | 2026 | - | 2026-11-27 | tax tables |
| [Guam](https://calcbloc.com/guam/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `guam` | USD | 2026 | - | 2026-11-27 | tax tables |
| [Guatemala](https://calcbloc.com/guatemala/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `guatemala` | GTQ | 2026 | - | 2026-11-26 | tax tables |
| [Guernsey](https://calcbloc.com/guernsey/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `guernsey` | GBP | 2026 | - | 2026-11-27 | tax tables |
| [Guinea](https://calcbloc.com/guinea/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `guinea` | GNF | 2026 | - | 2026-11-27 | tax tables |
| [Guinea-Bissau](https://calcbloc.com/guinea-bissau/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `guinea-bissau` | XOF | 2026 | - | 2026-11-27 | tax tables |
| [Guyana](https://calcbloc.com/guyana/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `guyana` | GYD | 2026 | - | 2026-11-26 | tax tables |
| [Haiti](https://calcbloc.com/haiti/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `haiti` | HTG | 2026-27 | - | 2026-11-26 | tax tables |
| [Honduras](https://calcbloc.com/honduras/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `honduras` | HNL | 2026 | - | 2026-11-26 | tax tables |
| [Hong Kong](https://calcbloc.com/hong-kong/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `hong-kong` | HKD | 2026-27 | - | 2026-11-26 | tax tables |
| [Hungary](https://calcbloc.com/hungary/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `hungary` | HUF | 2026 | - | 2026-11-22 | tax tables |
| [Iceland](https://calcbloc.com/iceland/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `iceland` | ISK | 2026 | - | 2026-11-22 | tax tables |
| [India](https://calcbloc.com/india/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `india` | INR | 2026-27 | - | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [Indonesia](https://calcbloc.com/indonesia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `indonesia` | IDR | 2026 | - | 2026-11-22 | tax tables |
| [Ireland](https://calcbloc.com/ireland/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `ireland` | EUR | 2026 | - | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [Isle of Man](https://calcbloc.com/isle-of-man/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `isle-of-man` | GBP | 2026-27 | - | 2026-11-27 | tax tables |
| [Israel](https://calcbloc.com/israel/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `israel` | ILS | 2026 | - | 2026-11-22 | tax tables |
| [Italy](https://calcbloc.com/italy/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `italy` | EUR | 2026 | 21 | 2026-11-22 | tax tables |
| [Jamaica](https://calcbloc.com/jamaica/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `jamaica` | JMD | 2026 | - | 2026-11-26 | tax tables |
| [Japan](https://calcbloc.com/japan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `japan` | JPY | 2026 | - | 2026-11-27 | tax tables |
| [Jersey](https://calcbloc.com/jersey/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `jersey` | GBP | 2026 | - | 2026-11-26 | tax tables |
| [Jordan](https://calcbloc.com/jordan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `jordan` | JOD | 2026 | - | 2026-11-26 | tax tables |
| [Kazakhstan](https://calcbloc.com/kazakhstan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `kazakhstan` | KZT | 2026 | - | 2026-11-26 | tax tables |
| [Kenya](https://calcbloc.com/kenya/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `kenya` | KES | 2026 | - | 2026-11-26 | tax tables |
| [Kiribati](https://calcbloc.com/kiribati/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `kiribati` | AUD | 2026 | - | 2026-11-26 | tax tables |
| [Kosovo](https://calcbloc.com/kosovo/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `kosovo` | EUR | 2026 | - | 2026-11-26 | tax tables |
| [Kuwait](https://calcbloc.com/kuwait/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `kuwait` | KWD | 2026 | - | 2026-11-22 | tax tables |
| [Kyrgyzstan](https://calcbloc.com/kyrgyzstan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `kyrgyzstan` | KGS | 2026 | - | 2026-11-26 | tax tables |
| [Laos](https://calcbloc.com/laos/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `laos` | LAK | 2026 | - | 2026-11-27 | tax tables |
| [Latvia](https://calcbloc.com/latvia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `latvia` | EUR | 2026 | - | 2026-11-21 | tax tables |
| [Lesotho](https://calcbloc.com/lesotho/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `lesotho` | LSL | 2026-27 | - | 2026-11-26 | tax tables |
| [Liberia](https://calcbloc.com/liberia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `liberia` | LRD | 2026 | - | 2026-11-27 | tax tables |
| [Liechtenstein](https://calcbloc.com/liechtenstein/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `liechtenstein` | CHF | 2026 | - | 2026-11-22 | tax tables |
| [Lithuania](https://calcbloc.com/lithuania/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `lithuania` | EUR | 2026 | - | 2026-11-21 | tax tables |
| [Luxembourg](https://calcbloc.com/luxembourg/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `luxembourg` | EUR | 2026 | - | 2026-11-27 | tax tables |
| [Macao](https://calcbloc.com/macao/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `macao` | MOP | 2026 | - | 2026-11-26 | tax tables |
| [Madagascar](https://calcbloc.com/madagascar/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `madagascar` | MGA | 2026 | - | 2026-11-26 | tax tables |
| [Malawi](https://calcbloc.com/malawi/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `malawi` | MWK | 2026-27 | - | 2026-11-26 | tax tables |
| [Malaysia](https://calcbloc.com/malaysia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `malaysia` | MYR | 2026 | - | 2026-11-22 | tax tables |
| [Maldives](https://calcbloc.com/maldives/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `maldives` | MVR | 2026 | - | 2026-11-26 | tax tables |
| [Mali](https://calcbloc.com/mali/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `mali` | XOF | 2026 | - | 2026-11-26 | tax tables |
| [Malta](https://calcbloc.com/malta/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `malta` | EUR | 2026 | - | 2026-11-22 | tax tables |
| [Marshall Islands](https://calcbloc.com/marshall-islands/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `marshall-islands` | USD | 2026-27 | - | 2026-11-26 | tax tables |
| [Mauritania](https://calcbloc.com/mauritania/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `mauritania` | MRU | 2026 | - | 2026-11-26 | tax tables |
| [Mauritius](https://calcbloc.com/mauritius/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `mauritius` | MUR | 2026-27 | - | 2026-11-26 | tax tables |
| [Mexico](https://calcbloc.com/mexico/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `mexico` | MXN | 2026 | - | 2026-11-21 | tax tables |
| [Micronesia](https://calcbloc.com/micronesia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `micronesia` | USD | 2026 | - | 2026-11-27 | tax tables |
| [Moldova](https://calcbloc.com/moldova/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `moldova` | MDL | 2026 | - | 2026-11-26 | tax tables |
| [Monaco](https://calcbloc.com/monaco/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `monaco` | EUR | 2026 | - | 2026-11-26 | tax tables |
| [Montenegro](https://calcbloc.com/montenegro/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `montenegro` | EUR | 2026 | - | 2026-11-26 | tax tables |
| [Montserrat](https://calcbloc.com/montserrat/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `montserrat` | XCD | 2026 | - | 2026-11-27 | tax tables |
| [Morocco](https://calcbloc.com/morocco/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `morocco` | MAD | 2026 | - | 2026-11-26 | tax tables |
| [Mozambique](https://calcbloc.com/mozambique/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `mozambique` | MZN | 2026 | - | 2026-11-27 | tax tables |
| [Myanmar](https://calcbloc.com/myanmar/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `myanmar` | MMK | 2026-27 | - | 2026-11-26 | tax tables |
| [Namibia](https://calcbloc.com/namibia/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `namibia` | NAD | 2026-27 | - | 2026-11-26 | tax tables |
| [Nauru](https://calcbloc.com/nauru/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `nauru` | AUD | 2026-27 | - | 2026-11-27 | tax tables |
| [Nepal](https://calcbloc.com/nepal/tax-tables/2083-84?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `nepal` | NPR | 2083-84 | - | 2026-11-26 | tax tables |
| [Netherlands](https://calcbloc.com/netherlands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `netherlands` | EUR | 2026 | - | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [New Caledonia](https://calcbloc.com/new-caledonia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `new-caledonia` | XPF | 2026 | - | 2026-11-27 | tax tables |
| [New Zealand](https://calcbloc.com/new-zealand/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `new-zealand` | NZD | 2026-27 | - | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [Nicaragua](https://calcbloc.com/nicaragua/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `nicaragua` | NIO | 2026 | - | 2026-11-27 | tax tables |
| [Niger](https://calcbloc.com/niger/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `niger` | XOF | 2026 | - | 2026-11-26 | tax tables |
| [Nigeria](https://calcbloc.com/nigeria/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `nigeria` | NGN | 2026 | - | 2026-11-26 | tax tables |
| [Niue](https://calcbloc.com/niue/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `niue` | NZD | 2026-27 | - | 2026-11-27 | tax tables |
| [North Macedonia](https://calcbloc.com/north-macedonia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `north-macedonia` | MKD | 2026 | - | 2026-11-26 | tax tables |
| [Northern Mariana Islands](https://calcbloc.com/northern-mariana-islands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `northern-mariana-islands` | USD | 2026 | - | 2026-11-27 | tax tables |
| [Norway](https://calcbloc.com/norway/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `norway` | NOK | 2026 | - | 2026-11-22 | tax tables |
| [Oman](https://calcbloc.com/oman/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `oman` | OMR | 2026 | - | 2026-11-22 | tax tables |
| [Pakistan](https://calcbloc.com/pakistan/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `pakistan` | PKR | 2026-27 | - | 2026-11-22 | tax tables |
| [Palau](https://calcbloc.com/palau/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `palau` | USD | 2026 | - | 2026-11-27 | tax tables |
| [Palestine](https://calcbloc.com/palestine/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `palestine` | ILS | 2026 | - | 2026-11-27 | tax tables |
| [Panama](https://calcbloc.com/panama/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `panama` | USD | 2026 | - | 2026-11-26 | tax tables |
| [Papua New Guinea](https://calcbloc.com/papua-new-guinea/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `papua-new-guinea` | PGK | 2026 | - | 2026-11-26 | tax tables |
| [Paraguay](https://calcbloc.com/paraguay/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `paraguay` | PYG | 2026 | - | 2026-11-26 | tax tables |
| [Peru](https://calcbloc.com/peru/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `peru` | PEN | 2026 | - | 2026-11-26 | tax tables |
| [Philippines](https://calcbloc.com/philippines/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `philippines` | PHP | 2026 | - | 2026-11-26 | tax tables |
| [Pitcairn Islands](https://calcbloc.com/pitcairn-islands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `pitcairn-islands` | NZD | 2026 | - | 2026-11-27 | tax tables |
| [Poland](https://calcbloc.com/poland/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `poland` | PLN | 2026 | - | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [Portugal](https://calcbloc.com/portugal/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `portugal` | EUR | 2026 | 2 | 2026-11-27 | tax tables |
| [Puerto Rico](https://calcbloc.com/puerto-rico/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `puerto-rico` | USD | 2026 | - | 2026-11-27 | tax tables |
| [Qatar](https://calcbloc.com/qatar/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `qatar` | QAR | 2026 | - | 2026-11-22 | tax tables |
| [Republic of the Congo](https://calcbloc.com/congo/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `congo` | XAF | 2026 | - | 2026-11-26 | tax tables |
| [Romania](https://calcbloc.com/romania/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `romania` | RON | 2026 | - | 2026-11-22 | tax tables |
| [Russia](https://calcbloc.com/russia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `russia` | RUB | 2026 | - | 2026-11-26 | tax tables |
| [Rwanda](https://calcbloc.com/rwanda/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `rwanda` | RWF | 2026 | - | 2026-11-26 | tax tables |
| [Saint Helena](https://calcbloc.com/saint-helena/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `saint-helena` | SHP | 2026-27 | - | 2026-11-27 | tax tables |
| [Saint Kitts and Nevis](https://calcbloc.com/saint-kitts-and-nevis/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `saint-kitts-and-nevis` | XCD | 2026 | - | 2026-11-26 | tax tables |
| [Saint Lucia](https://calcbloc.com/saint-lucia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `saint-lucia` | XCD | 2026 | - | 2026-11-26 | tax tables |
| [Saint Pierre and Miquelon](https://calcbloc.com/saint-pierre-and-miquelon/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `saint-pierre-and-miquelon` | EUR | 2026 | - | 2026-11-27 | tax tables |
| [Saint Vincent and the Grenadines](https://calcbloc.com/saint-vincent-and-the-grenadines/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `saint-vincent-and-the-grenadines` | XCD | 2026 | - | 2026-11-26 | tax tables |
| [Saint-Barthélemy](https://calcbloc.com/saint-barthelemy/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `saint-barthelemy` | EUR | 2026 | - | 2026-11-27 | tax tables |
| [Saint-Martin](https://calcbloc.com/saint-martin/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `saint-martin` | EUR | 2026 | - | 2026-11-27 | tax tables |
| [Samoa](https://calcbloc.com/samoa/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `samoa` | WST | 2026 | - | 2026-11-26 | tax tables |
| [San Marino](https://calcbloc.com/san-marino/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `san-marino` | EUR | 2026 | - | 2026-11-26 | tax tables |
| [Saudi Arabia](https://calcbloc.com/saudi-arabia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `saudi-arabia` | SAR | 2026 | - | 2026-11-22 | tax tables |
| [Senegal](https://calcbloc.com/senegal/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `senegal` | XOF | 2026 | - | 2026-11-26 | tax tables |
| [Serbia](https://calcbloc.com/serbia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `serbia` | RSD | 2026 | - | 2026-11-22 | tax tables |
| [Seychelles](https://calcbloc.com/seychelles/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `seychelles` | SCR | 2026 | - | 2026-11-26 | tax tables |
| [Sierra Leone](https://calcbloc.com/sierra-leone/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `sierra-leone` | SLE | 2026 | - | 2026-11-26 | tax tables |
| [Singapore](https://calcbloc.com/singapore/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `singapore` | SGD | 2026 | - | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [Sint Maarten](https://calcbloc.com/sint-maarten/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `sint-maarten` | XCG | 2026 | - | 2026-11-27 | tax tables |
| [Slovakia](https://calcbloc.com/slovakia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `slovakia` | EUR | 2026 | - | 2026-11-22 | tax tables |
| [Slovenia](https://calcbloc.com/slovenia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `slovenia` | EUR | 2026 | - | 2026-11-22 | tax tables |
| [Solomon Islands](https://calcbloc.com/solomon-islands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `solomon-islands` | SBD | 2026 | - | 2026-11-26 | tax tables |
| [Somalia](https://calcbloc.com/somalia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `somalia` | SOS | 2026 | - | 2026-11-27 | tax tables |
| [South Africa](https://calcbloc.com/south-africa/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `south-africa` | ZAR | 2026-27 | - | 2026-11-22 | tax tables |
| [South Korea](https://calcbloc.com/south-korea/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `south-korea` | KRW | 2026 | - | 2026-11-21 | tax tables |
| [South Sudan](https://calcbloc.com/south-sudan/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `south-sudan` | SSP | 2026-27 | - | 2026-11-27 | tax tables |
| [Spain](https://calcbloc.com/spain/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `spain` | EUR | 2026 | 21 | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [Sri Lanka](https://calcbloc.com/sri-lanka/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `sri-lanka` | LKR | 2026-27 | - | 2026-11-26 | tax tables |
| [Sudan](https://calcbloc.com/sudan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `sudan` | SDG | 2026 | - | 2026-11-27 | tax tables |
| [Suriname](https://calcbloc.com/suriname/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `suriname` | SRD | 2026 | - | 2026-11-26 | tax tables |
| [Sweden](https://calcbloc.com/sweden/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `sweden` | SEK | 2026 | 21 | 2026-11-22 | tax tables |
| [Switzerland](https://calcbloc.com/switzerland/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `switzerland` | CHF | 2026 | 26 | 2026-11-22 | tax tables |
| [Taiwan](https://calcbloc.com/taiwan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `taiwan` | TWD | 2026 | - | 2026-11-26 | tax tables |
| [Tajikistan](https://calcbloc.com/tajikistan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `tajikistan` | TJS | 2026 | - | 2026-11-26 | tax tables |
| [Tanzania](https://calcbloc.com/tanzania/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `tanzania` | TZS | 2026 | - | 2026-11-26 | tax tables |
| [Thailand](https://calcbloc.com/thailand/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `thailand` | THB | 2026 | - | 2026-11-22 | tax tables |
| [The Gambia](https://calcbloc.com/gambia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `gambia` | GMD | 2026 | - | 2026-11-27 | tax tables |
| [Timor-Leste](https://calcbloc.com/timor-leste/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `timor-leste` | USD | 2026 | - | 2026-11-27 | tax tables |
| [Togo](https://calcbloc.com/togo/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `togo` | XOF | 2026 | - | 2026-11-26 | tax tables |
| [Tonga](https://calcbloc.com/tonga/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `tonga` | TOP | 2026-27 | - | 2026-11-26 | tax tables |
| [Trinidad and Tobago](https://calcbloc.com/trinidad-and-tobago/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `trinidad-and-tobago` | TTD | 2026 | - | 2026-11-26 | tax tables |
| [Tunisia](https://calcbloc.com/tunisia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `tunisia` | TND | 2026 | - | 2026-11-26 | tax tables |
| [Turkmenistan](https://calcbloc.com/turkmenistan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `turkmenistan` | TMT | 2026 | - | 2026-11-27 | tax tables |
| [Turks and Caicos Islands](https://calcbloc.com/turks-and-caicos-islands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `turks-and-caicos-islands` | USD | 2026 | - | 2026-11-27 | tax tables |
| [Tuvalu](https://calcbloc.com/tuvalu/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `tuvalu` | AUD | 2026 | - | 2026-11-27 | tax tables |
| [Türkiye](https://calcbloc.com/turkey/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `turkey` | TRY | 2026 | - | 2026-11-27 | tax tables |
| [US Virgin Islands](https://calcbloc.com/us-virgin-islands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `us-virgin-islands` | USD | 2026 | - | 2026-11-27 | tax tables |
| [Uganda](https://calcbloc.com/uganda/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `uganda` | UGX | 2026-27 | - | 2026-11-26 | tax tables |
| [Ukraine](https://calcbloc.com/ukraine/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `ukraine` | UAH | 2026 | - | 2026-11-22 | tax tables |
| [United Arab Emirates](https://calcbloc.com/uae/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `uae` | AED | 2026 | - | 2026-11-21 | tax tables |
| [United Kingdom](https://calcbloc.com/uk/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `uk` | GBP | 2026-27, 2025-26 | 2 | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [United States](https://calcbloc.com/us/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `us` | USD | 2026 | 51 | 2026-11-22 | tax tables, net-salary, employer-cost, compare |
| [Uruguay](https://calcbloc.com/uruguay/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `uruguay` | UYU | 2026 | - | 2026-11-26 | tax tables |
| [Uzbekistan](https://calcbloc.com/uzbekistan/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `uzbekistan` | UZS | 2026 | - | 2026-11-26 | tax tables |
| [Vanuatu](https://calcbloc.com/vanuatu/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `vanuatu` | VUV | 2026 | - | 2026-11-26 | tax tables |
| [Vietnam](https://calcbloc.com/vietnam/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `vietnam` | VND | 2026 | - | 2026-11-22 | tax tables |
| [Wallis and Futuna](https://calcbloc.com/wallis-and-futuna/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `wallis-and-futuna` | XPF | 2026 | - | 2026-11-27 | tax tables |
| [Zambia](https://calcbloc.com/zambia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `zambia` | ZMW | 2026 | - | 2026-11-26 | tax tables |
| [Zimbabwe](https://calcbloc.com/zimbabwe/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `zimbabwe` | ZWG | 2026 | - | 2026-11-26 | tax tables |
<!-- coverage:end -->

Countries marked "bundled only" are included in the data ahead of their pages on calcbloc.com. `Client::countries()` lists what the API serves today.

## How the data is tested

The test suite (PHPUnit, no network) checks:

- **Official worked examples.** Tax computed with `taxFromBands()` from the bundled tables matches figures that tax authorities publish themselves:
  - Ireland, Universal Social Charge 2026: all four examples on Revenue's [Calculating your USC](https://www.revenue.ie/en/jobs-and-pensions/usc/calculating-usc.aspx) page (for example, EUR 50,000 gives EUR 1,032.82).
  - Singapore, resident income tax: every "Gross Tax Payable" amount in IRAS's [Individual Income Tax rates](https://www.iras.gov.sg/taxes/individual-income-tax/basics-of-individual-income-tax/tax-residency-and-tax-rates/individual-income-tax-rates) table, up to SGD 199,150 on the first SGD 1,000,000.
  - United States, federal income tax 2026: the tentative amounts in the IRS [Publication 15-T](https://www.irs.gov/pub/irs-pdf/p15t.pdf) (2026) percentage method tables, for single, married filing jointly and head of household filers.
  - New Zealand, income tax 2026-27: the PAYE formulas in section 5.2 of Inland Revenue's [payroll calculations and business rules specification](https://www.ird.govt.nz/-/media/project/ir/home/documents/digital-service-providers/software-providers/payroll-calculations-business-rules-specifications/payroll-calculations-and-business-rules-specification.pdf).
- **Schema.** Every row of every bundled table has a `source_url` and a `retrieved_at` date. Sections are well formed, and bands are ascending, with decimal rates.
- **Integrity.** The bundled files leave out the flat `rows` array to keep the package small (about 2 MB for every country, year and region), and the loader rebuilds it. The checksums in `tests/data-integrity.json` prove that the rebuilt tables are identical to the source files.
- **Client.** Requests, headers, response parsing and every error path, against a fake transport.

```bash
composer install
composer test       # PHPUnit
composer analyse    # PHPStan, level 8
```

## Updating the bundled data

The tables come from the [calcbloc-data](https://github.com/itmarkerz/calcbloc-data) repository. With it checked out next to this one:

```bash
composer sync-tables                                  # copies ../calcbloc-data/tables into data/
php bin/sync-tables.php --from /path/to/tables        # or CALCBLOC_TABLES_DIR=/path/to/tables
php bin/coverage-table.php --countries countries.json --write README.md   # refresh the coverage table
```

The script reads the country list from `manifest.json`, checks every row for a source URL and date, and rewrites `tests/data-integrity.json`.

## Licences and attribution

- **Copyright:** © 2026 itmarkerz technologies (code and data). CalcBloc is a product of itmarkerz technologies.
- **Code:** [MIT](LICENSE).
- **Data** (`data/`): [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) (full text in [`data/LICENSE`](data/LICENSE)). When you show or publish figures from it, credit **"Data by CalcBloc (calcbloc.com/data)"** with a link to [calcbloc.com/data](https://calcbloc.com/data?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss).
- The canonical, most recent version of each table is its country page on calcbloc.com.
- **API:** free use requires a visible "Data by CalcBloc" link wherever the figures are shown. Every response includes an `attribution` block with the wording and the page to link to. See the [API terms](https://calcbloc.com/developers/terms?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss).

The tables are reference data compiled from official sources. They are not tax, legal or financial advice. Check the cited source before relying on a figure.

## Links

- API documentation and free keys: [calcbloc.com/developers](https://calcbloc.com/developers?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss)
- Data downloads and changelog: [calcbloc.com/data](https://calcbloc.com/data?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss)
- OpenAPI 3.1 description: [calcbloc.com/api/openapi.json](https://calcbloc.com/api/openapi.json)
- Other packages: [calcbloc-python](https://github.com/itmarkerz/calcbloc-python), [calcbloc-js](https://github.com/itmarkerz/calcbloc-js), [calcbloc-data](https://github.com/itmarkerz/calcbloc-data)

## About

[CalcBloc](https://calcbloc.com/?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) is built and maintained by [itmarkerz technologies](https://itmarkerz.co.in/?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss), a web and mobile app development company in Kerala, India. The open data behind this package is at [calcbloc.com/data](https://calcbloc.com/data?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) and the API documentation at [calcbloc.com/developers](https://calcbloc.com/developers?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss). The other CalcBloc packages (Python, JavaScript, R, Google Sheets, the MCP server and the dataset itself) are in the [itmarkerz GitHub organization](https://github.com/itmarkerz).
