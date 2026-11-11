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
- **API client.** A typed client for the [CalcBloc API](https://calcbloc.com/developers?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss): tax tables for every country, year and region, plus net salary, employer cost and two-country salary comparison from the same engines as the calculators on calcbloc.com.

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

$client = new Client(); // no key (50 requests a day), or CALCBLOC_API_KEY; new Client(apiKey: 'cb_...') to pass one

$countries = $client->countries();                              // list<ApiCountry>: years, regions, endpoints and their parameters
$table     = $client->taxTables('canada', 'latest', 'on');      // TaxTable (same object as Tables::load())
$csv       = $client->taxTables('us', '2026', 'ca', 'csv');     // CSV text, one row per band or value

$pay  = $client->netSalary('uk', 50000, ['region' => 'scotland']);    // CalculationResult
$pay->summary['netAnnual'];                                            // plus results, breakdown, notes, calculatorUrl
$cost = $client->employerCost('germany', 65000);                      // CalculationResult: summary['totalCost'], ...
$both = $client->compare('uk', 'ireland', 60000, ['currency' => 'GBP']); // ComparisonResult
```

Each country's parameters for `net-salary` and `employer-cost` (for example `filing_status` in the US or `tax_class` in Germany) are listed by `countries()` and in the [OpenAPI description](https://calcbloc.com/api/openapi.json). Countries marked `tablesOnly` have tax tables but no calculation engine yet. The calculation endpoints answer them with HTTP 400 `unsupported_country`.

### Free use and API keys

- **Offline tables need no key and no network**: `Tables` reads the JSON bundled in the package.
- **API calls work without a key** for 50 requests a day and 5 a minute per IP address, on every endpoint.
- **Add a free API key for 5,000 requests a day** (60 a minute). Get one instantly at [calcbloc.com/developers/keys](https://calcbloc.com/developers/keys?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) (enter your email address; the key is shown once).
- **Set it** with `new Client(apiKey: 'cb_...')`, or in the `CALCBLOC_API_KEY` environment variable, which `new Client()` reads when no key is passed (pass `''` to call without a key even when the variable is set). The client sends it in the `X-API-Key` header. Keys in the URL are refused.

| Access | Per day | Per minute |
| --- | --- | --- |
| No key (per IP address) | 50 | 5 |
| Free key | 5,000 | 60 |

When the free allowance without a key is used up, the client throws a `RateLimitException` whose message says so, links to the keys page and shows how to set the key. With a key, the message gives that key's limit.

Days reset at midnight UTC. `$client->lastRateLimit()` returns the `X-RateLimit-*` values of the last response. Current limits and commercial terms are on the [developer page](https://calcbloc.com/developers?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss#limits). Responses can be cached for a day, so cache on your side, or use the offline tables when the bundled year is the one you need.

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
| Network, key | None | HTTPS; 50 requests a day without a key, 5,000 with a free key |
| Content | Tax tables as of this release | Always the current published tables |
| Calculations | `taxFromBands()` on one set of brackets | Full net salary, employer cost and comparison engines |
| Limits | None | See [free use and API keys](#free-use-and-api-keys) |

Tax figures change: budgets, mid-year changes and corrections. The tables are dated (`lastVerified`, and `retrieved_at` on every row). Update the package regularly, or call the API when you need the latest figures.

## Coverage

Generated from `data/manifest.json` by `composer coverage-table`. "Regions" counts the regional tables bundled (US states and DC, Canadian provinces and territories, UK nations, Spanish autonomous communities, Swiss cantons and so on).

<!-- coverage:start -->
| Country | Code | Currency | Tax years | Regions | Last verified | API endpoints |
| --- | --- | --- | --- | --- | --- | --- |
| [Australia](https://calcbloc.com/australia/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `australia` | AUD | 2026-27 | - | 2026-11-09 | tax tables, net-salary, employer-cost, compare |
| [Austria](https://calcbloc.com/austria/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `austria` | EUR | 2026 | - | 2026-11-10 | tax tables |
| [Canada](https://calcbloc.com/canada/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `canada` | CAD | 2026 | 13 | 2026-11-09 | tax tables, net-salary, employer-cost, compare |
| [Czechia](https://calcbloc.com/czechia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `czechia` | CZK | 2026 | - | 2026-11-11 | tax tables |
| [Denmark](https://calcbloc.com/denmark/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `denmark` | DKK | 2026 | 5 | 2026-11-09 | tax tables |
| [Finland](https://calcbloc.com/finland/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `finland` | EUR | 2026 | 19 | 2026-11-11 | tax tables |
| [France](https://calcbloc.com/france/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `france` | EUR | 2026 | - | 2026-11-09 | tax tables, net-salary, compare |
| [Germany](https://calcbloc.com/germany/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `germany` | EUR | 2026 | - | 2026-11-09 | tax tables, net-salary, employer-cost, compare |
| [Greece](https://calcbloc.com/greece/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `greece` | EUR | 2026 | - | 2026-11-11 | tax tables |
| [Hungary](https://calcbloc.com/hungary/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `hungary` | HUF | 2026 | - | 2026-11-11 | tax tables |
| [India](https://calcbloc.com/india/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `india` | INR | 2026-27 | - | 2026-11-11 | tax tables, net-salary, employer-cost, compare |
| [Ireland](https://calcbloc.com/ireland/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `ireland` | EUR | 2026 | - | 2026-11-06 | tax tables, net-salary, employer-cost, compare |
| [Italy](https://calcbloc.com/italy/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `italy` | EUR | 2026 | 21 | 2026-11-09 | tax tables |
| [Luxembourg](https://calcbloc.com/luxembourg/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `luxembourg` | EUR | 2026 | - | 2026-11-11 | tax tables |
| [Netherlands](https://calcbloc.com/netherlands/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `netherlands` | EUR | 2026 | - | 2026-11-09 | tax tables, net-salary, employer-cost, compare |
| [New Zealand](https://calcbloc.com/new-zealand/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `new-zealand` | NZD | 2026-27 | - | 2026-11-09 | tax tables, net-salary, employer-cost, compare |
| [Norway](https://calcbloc.com/norway/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `norway` | NOK | 2026 | - | 2026-11-11 | tax tables |
| [Poland](https://calcbloc.com/poland/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `poland` | PLN | 2026 | - | 2026-11-10 | tax tables |
| [Portugal](https://calcbloc.com/portugal/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `portugal` | EUR | 2026 | 2 | 2026-11-09 | tax tables |
| [Romania](https://calcbloc.com/romania/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `romania` | RON | 2026 | - | 2026-11-11 | tax tables |
| [Singapore](https://calcbloc.com/singapore/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `singapore` | SGD | 2026 | - | 2026-11-09 | tax tables, net-salary, employer-cost, compare |
| [Slovakia](https://calcbloc.com/slovakia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `slovakia` | EUR | 2026 | - | 2026-11-11 | tax tables |
| [Slovenia](https://calcbloc.com/slovenia/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `slovenia` | EUR | 2026 | - | 2026-11-11 | tax tables |
| [Spain](https://calcbloc.com/spain/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `spain` | EUR | 2026 | 21 | 2026-11-09 | tax tables, net-salary, employer-cost, compare |
| [Sweden](https://calcbloc.com/sweden/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `sweden` | SEK | 2026 | 21 | 2026-11-10 | tax tables |
| [Switzerland](https://calcbloc.com/switzerland/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `switzerland` | CHF | 2026 | 26 | 2026-11-09 | tax tables |
| [United Arab Emirates](https://calcbloc.com/uae/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `uae` | AED | 2026 | - | 2026-11-09 | tax tables |
| [United Kingdom](https://calcbloc.com/uk/tax-tables/2026-27?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `uk` | GBP | 2026-27, 2025-26 | 2 | 2026-11-09 | tax tables, net-salary, employer-cost, compare |
| [United States](https://calcbloc.com/us/tax-tables/2026?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) | `us` | USD | 2026 | 51 | 2026-11-09 | tax tables, net-salary, employer-cost, compare |
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
- **API:** free use requires a visible "Data by CalcBloc" link wherever the figures are shown. Every response includes an `attribution` block with the wording and the page to link to. See the [API terms](https://calcbloc.com/developers/terms?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss).

The tables are reference data compiled from official sources. They are not tax, legal or financial advice. Check the cited source before relying on a figure.

## Links

- API documentation and free keys: [calcbloc.com/developers](https://calcbloc.com/developers?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss)
- Data downloads and changelog: [calcbloc.com/data](https://calcbloc.com/data?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss)
- OpenAPI 3.1 description: [calcbloc.com/api/openapi.json](https://calcbloc.com/api/openapi.json)
- Other packages: [calcbloc-python](https://github.com/itmarkerz/calcbloc-python), [calcbloc-js](https://github.com/itmarkerz/calcbloc-js), [calcbloc-data](https://github.com/itmarkerz/calcbloc-data)

## About

[CalcBloc](https://calcbloc.com/?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) is built and maintained by [itmarkerz technologies](https://itmarkerz.co.in/?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss), a web and mobile app development company in Kerala, India. The open data behind this package is at [calcbloc.com/data](https://calcbloc.com/data?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss) and the API documentation at [calcbloc.com/developers](https://calcbloc.com/developers?utm_source=calcbloc-php&utm_medium=readme&utm_campaign=itmarkerz-oss). The other CalcBloc packages (Python, JavaScript, R, Google Sheets, the MCP server and the dataset itself) are in the [itmarkerz GitHub organization](https://github.com/itmarkerz).
