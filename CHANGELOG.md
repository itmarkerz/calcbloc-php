# Changelog

All notable changes to `calcbloc/calcbloc` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).
Changes to the tax figures themselves are listed in the [CalcBloc data changelog](https://calcbloc.com/data/changelog?utm_source=calcbloc-php&utm_medium=docs&utm_campaign=itmarkerz-oss).

## [Unreleased]

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
