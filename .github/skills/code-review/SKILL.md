---
name: code-review
description: Review pull requests in florianv/exchanger (PHP currency exchange rate library). Use when reviewing any PR or diff that adds or changes a provider in src/Service/, its tests and fixtures in tests/, the Registry, or the provider tables in README.md and doc/readme.md.
---

# Code review for exchanger

Most pull requests add a new exchange rate provider. Review them against the existing services in `src/Service/`, not against general PHP conventions.

## Procedure

1. Classify every changed file: service, registry, test, fixture, docs, CI.
2. Verify claims about a provider's API (endpoint, auth, response shape, error responses) against its public documentation. Do not assume an error format the docs do not describe.
3. Report a finding only when you can name the concrete response or input that breaks and the smallest fix.

## Error mapping

This is where most provider bugs are. `Chain` and callers rely on the exception class to decide what to do next.

- `UnsupportedCurrencyPairException` means the provider answered and does not support the pair. Flag any path where a transport failure reaches it: non-200 status, empty body, HTML gateway page, plain-text 404, malformed JSON (`json_decode()` returning null).
- Prefer `StringUtil::jsonToArray()` and `StringUtil::xmlToElement()` over `json_decode()` and `simplexml_load_string()`; they throw on invalid input.
- A documented "invalid currency" error (often HTTP 400 or 422) maps to `UnsupportedCurrencyPairException`. `ExchangeRatesApi` is the reference.
- A date outside the provider's range maps to `UnsupportedDateException`, and `supportQuery()` should reject it up front when the range is known. Name the cutoff as a constant.
- A generic error message should keep the HTTP status code, and a caught exception should be passed as `previous`.
- A rate of zero, a negative rate or a non-numeric rate is not a valid `ExchangeRate`.

## Service structure

- Historical support uses the `SupportsHistoricalQueries` trait with `getLatestExchangeRate()` and `getHistoricalExchangeRate()`. Flag a hand-rolled `getExchangeRate()` override with an `instanceof` branch.
- Type checks and imports use the contracts in `Exchanger\Contract\`. An `instanceof Exchanger\HistoricalExchangeRateQuery` silently treats other contract implementations as latest-rate queries.
- `supportQuery()` must return false for anything `getExchangeRate()` cannot answer correctly, for example historical queries on a latest-only provider.
- Required options are validated in `processOptions()`. An API key concatenated into a header should be a non-empty, trimmed string: a trailing newline makes PSR-7 stacks throw an exception whose message contains the key.
- Currency codes from the response are compared case-insensitively, or the query codes are normalized; `CurrencyPair`'s constructor does not uppercase them.
- Class names lowercase acronyms (`UniRateApi`, `XchangeApi`, `FastForex`).

## Registry and docs

- The Registry identifier is the snake_case of the class name (`unirate_api`, `national_bank_of_denmark`). Identifiers are public API, so naming must be settled before a release.
- A new provider needs a row in the README provider table and in the matching table in `doc/readme.md` ("Commercial services" or "Public services"). Check that base and quote currency columns are correct.
- A docs diff should add one row, not reflow the padding of the whole table.

## Tests and fixtures

- Tests use `#[Test]` with snake_case method names. A `/** @test */` docblock runs zero tests on PHPUnit 12, while the PHP 8.2 job (PHPUnit 11) stays green.
- Reuse `ServiceTestCase::getHttpAdapterMock()` (it takes a status code and an optional request callback) instead of a new mock helper.
- Each error branch needs a test that only that branch can satisfy. A test that expects `Exception::class` also passes when `UnsupportedCurrencyPairException` is thrown, and an empty 500 body never reaches a status-code check.
- Prefer fixtures captured from the real API, including its documented error responses. Trim them to the currencies the assertions use.
- New PHP files carry `declare(strict_types=1)` and the `This file is part of Exchanger` licence header.

## Do not flag

- Anything already reported by CI (PHPUnit, Psalm, backward compatibility check).
- Formatting that `composer cs:fix` would change. Mention once in the summary that `composer cs:check` fails, without one comment per line.
- `#[\Override]` placement; Psalm enforces it.
