# Changelog

All notable changes to `calcbloc/calcbloc` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).
Changes to the tax figures themselves are listed in the [CalcBloc data changelog](https://calcbloc.com/data/changelog?utm_source=calcbloc-php&utm_medium=docs&utm_campaign=itmarkerz-oss).

## [0.2.0] - Unreleased

### Added

- For CalcBloc API 1.10: `Client::taxTreaties()` for GET /api/v1/tax-treaties, the double taxation treaties stored for
  a country (India), with the maximum withholding rates on dividends, interest, royalties and fees for technical
  services, their articles and wording, the relief methods, dates and the official source (an array).
- For CalcBloc API 1.10: `CalculationResult::$verification` (`official-example`, `law-text` or null) and
  `ApiCountry::verification($endpoint)`. With a value, the first note says how the calculation was checked.
- For CalcBloc API 1.10: take-home pay and employer cost for Denmark (`dk`: `municipality`, `church`, `atp_hours`),
  Estonia (`ee`: `funded_pension`, `pension_age`) and Poland (`pl`: `accident_rate_pct`, `commuter`) work through
  `netSalary()` and `employerCost()` as they are; `compare()` accepts `dk`, `ee` and `pl`.
- Bundled tables: 217 countries (422 tables), synced from calcbloc-data 2026-11-27; Belgium stays pending. Short
  codes, ISO codes and names for the 84 new countries in `CountryCodes`; the README coverage table lists all 217.
  The schema test also accepts band rates above 100% for Laos's excise on fuel-powered cars (up to 220%).
- Tests use real API 1.10 responses as fixtures (`tests/fixtures/api-1.10/`).

- For CalcBloc API 1.8 and 1.9: `Client::supplementalWithholding()`, `equityTax()`, `crossBorderEquity()`, `facts()`,
  `taxCalendar()`, `tariffs()`, `tradeAgreements()` and `importDuty()`. They return the API's JSON as an array.
- Bundled tables: 133 countries (338 tables), synced from calcbloc-data 2026-11-26; Belgium stays pending. Short
  codes, ISO codes and names for all of them in `CountryCodes`.
- The flat `rows` are kept in the data files: since API 1.8 they carry columns the sections do not hold
  (`tax_type`, `jurisdiction`, `level`, `table_version`). The schema test accepts band rates above 100% for an ad
  valorem vehicle registration tax (Hong Kong).

### Changed

- For CalcBloc API 1.7: the keyless limits are now 300 requests a day and 20 a minute per IP address
  (see [calcbloc.com/developers#limits](https://calcbloc.com/developers#limits)). The `RateLimitException` message
  without a key no longer states fixed numbers: it quotes the daily limit from the `X-RateLimit-Limit` header the
  client last received ("The free daily allowance without a key (300 requests a day) is used up."), or says it
  without a number before any response has carried one, and still links to a free email-only key and
  `CALCBLOC_API_KEY`. PHPDoc describes the limits in general terms and links to the limits page.
- Docs: API calls accept short country codes (`nz`, `de`, `ae`, `uk`), the US `filing_status` example uses `mfj`,
  `region` is required for take-home pay in `us`/`ca` and for employer cost in `us`/`au`/`ca`, and equity
  compensation, bonus/supplemental and cross-border withholding are noted as not supported yet.
- `Tables::load()` and `Tables::country()` accept, case-insensitively, each bundled country's short code (`nz`,
  `de`; `uk` or `gb`), ISO code (`NZ`) and English name (`New Zealand`) as well as the key (`new-zealand`), and
  `Client::taxTables()` maps them to the key before building the `/tax-tables/{country}` path. The mapping mirrors
  the API's country codes.
- `Client` reads the API key from the `CALCBLOC_API_KEY` environment variable when none is passed (`''` still means
  no key) and has `hasApiKey()`. Without a key every endpoint works within the keyless allowance; a
  `RateLimitException` without a key says the free allowance is used up, links to a free API key
  (`Client::KEYS_URL`) and shows how to set it.
- Bundled data refreshed to the CalcBloc data manifest of 2026-11-13: 41 countries and 246 tables. New: Latvia,
  Croatia (with 21 regional tables), Estonia, Bulgaria, Lithuania, Malta, Cyprus, Iceland, Ukraine, Liechtenstein,
  Serbia and Türkiye.

## [0.1.0] - 2026-11-11

First release (not yet published on Packagist).

### Added

- `CalcBloc\Tables`: offline tax tables with `load()`, `listCountries()`, `country()`, `manifest()`, `useDirectory()`
  and `taxFromBands()`. Bundled data covers every country, tax year and region in the CalcBloc data manifest of
  2026-11-11, in the shape of the API's `/api/v1/tax-tables` responses (data licence CC BY 4.0).
- `CalcBloc\Client` for API 1.2.0: `countries()`, `taxTables()` (JSON or CSV, `latest` year, regions), `netSalary()`,
  `employerCost()`, `compare()` and `lastRateLimit()`. The API key is sent in the `X-API-Key` header.
- Typed value objects: `TaxTable`, `Section`, `Source`, `Region`, `CountryInfo`, `ApiCountry`, `CalculationResult`,
  `ComparisonResult`, `RateLimit`.
- Exceptions: `CalcBlocException` (API error code, HTTP status, docs link), `RateLimitException` (Retry-After),
  `TransportException`.
- HTTP transports: ext-curl, PHP streams, and an adapter for any PSR-18 client (no PSR dependency required).
- Tests against official worked examples from Revenue (Ireland USC), IRAS (Singapore), the IRS (Publication 15-T)
  and Inland Revenue (New Zealand PAYE specification), plus schema, data integrity and client tests.
- `bin/sync-tables.php` to refresh `data/` from the calcbloc-data repository and `bin/coverage-table.php` for the
  README coverage table.

[Unreleased]: https://github.com/itmarkerz/calcbloc-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/itmarkerz/calcbloc-php/releases/tag/v0.1.0
