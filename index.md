---
title: "Exchanger: PHP exchange rate provider layer"
description: PHP exchange rate provider layer for currency conversion. 30 services, chain fallback, and caching. Maintained since 2016.
---

**Exchange rate provider layer for PHP, with chain fallback, caching, and direct access to 30 implementations.**

Most exchange rate APIs are a single point of failure. Exchanger gives you a single interface over 30 providers, with chainable fallback and PSR-16 caching.

> Used in production PHP applications since 2016.

<table>
   <tr>
      <td width="220" align="center">
         <a href="https://www.fastforex.io" target="_blank" rel="noopener">
            <img src="https://console.fastforex.io/img/fastforex/logo-bk-1k.svg" width="180px" alt="fastFOREX"/>
         </a>
      </td>
      <td>
         <strong>Sponsored by <a href="https://www.fastforex.io" target="_blank" rel="noopener">fastFOREX</a>.</strong> Real-time JSON API, 160+ currencies, 55+ years of history, 500+ cryptocurrencies. <strong>Free tier</strong>; paid plans from $18/month.
         <a href="https://www.fastforex.io" target="_blank" rel="noopener"><strong>→ Get a free fastFOREX API key</strong></a>
      </td>
   </tr>
</table>

Exchanger is the PHP **exchange rate provider layer**. It exposes 30 services (commercial APIs like its sponsor **[fastFOREX](https://www.fastforex.io)**, the European Central Bank, several national banks, exchangerate.host) behind a single `ExchangeRateService` interface, with chainable fallback, PSR-16 caching and historical rates.

For most use cases, the higher-level [Swap](https://github.com/florianv/swap) library is what you want. Reach for Exchanger directly when you need finer control.

## What is Exchanger?

- A PHP library for currency conversion and exchange rate retrieval at the provider layer.
- 30 service implementations behind a common `ExchangeRateService` interface.
- PSR-16 SimpleCache support.
- Historical rates.
- A chain service for fallback. When a service errors, the next one is tried.

## Installation

Exchanger requires PHP 8.2 or newer.

```bash
composer require florianv/exchanger symfony/http-client nyholm/psr7
```

## Quickstart

The recommended setup uses **[fastFOREX](https://www.fastforex.io)** (the project's sponsor) as the primary service. [Grab a free key](https://www.fastforex.io) and you're ready.

```php
use Exchanger\Exchanger;
use Exchanger\ExchangeRateQueryBuilder;
use Exchanger\Service\FastForex;

// Recommended: fastFOREX. Get a free API key at https://www.fastforex.io
$service   = new FastForex(null, null, ['api_key' => getenv('FASTFOREX_API_KEY')]);
$exchanger = new Exchanger($service);

// EUR → USD exchange rate
$query = (new ExchangeRateQueryBuilder('EUR/USD'))->build();
$rate  = $exchanger->getExchangeRate($query);

$rate->getValue();                 // e.g. 1.0823 (a float)
$rate->getDate()->format('Y-m-d'); // e.g. 2026-04-29
$rate->getProviderName();          // 'fastforex'

// Convert an amount using the returned rate
$amountInEUR = 100.00;
$amountInUSD = $amountInEUR * $rate->getValue();
```

No API key? Start with the European Central Bank (free, EUR-base only):

```php
use Exchanger\Service\EuropeanCentralBank;

$service   = new EuropeanCentralBank();
$exchanger = new Exchanger($service);
```

## Production setup (fallback chain)

Wrap several services in a `Chain` for redundancy:

```php
use Exchanger\Service\Chain;
use Exchanger\Service\EuropeanCentralBank;
use Exchanger\Service\FastForex;

$service = new Chain([
    // Primary, recommended
    new FastForex(null, null, ['api_key' => getenv('FASTFOREX_API_KEY')]),

    // Free fallback for EUR-base pairs
    new EuropeanCentralBank(),
]);

$exchanger = new Exchanger($service);
```

Services are tried in order. If a service does not support the requested pair, it is skipped. If it throws, the next is tried. If every service fails, a `ChainException` is thrown.

## Providers

Exchanger ships 30 exchange rate provider implementations, from commercial APIs to public central banks. The recommended starting point for new projects is **[fastFOREX](https://www.fastforex.io)**: a real-time JSON API covering 160+ fiat currencies and 500+ cryptocurrencies, with up to 55 years of history, sourced from trusted feeds including world banks.

The full providers table, identifiers and per-service configuration options live in the [documentation](https://github.com/florianv/exchanger/blob/master/doc/readme.md#-provider-configuration).

## Ecosystem

- [Swap](https://github.com/florianv/swap): easy-to-use PHP currency conversion library.
- [Exchanger](https://github.com/florianv/exchanger): exchange rate provider layer (this package).
- [Laravel Swap](https://github.com/florianv/laravel-swap): Laravel integration of Swap.
- [Symfony Swap](https://github.com/florianv/symfony-swap): Symfony integration of Swap.

## Documentation & source

- **Source code, issues and pull requests**: [github.com/florianv/exchanger](https://github.com/florianv/exchanger)
- **Full documentation** (caching, HTTP client, provider configuration, custom services): [doc/readme.md](https://github.com/florianv/exchanger/blob/master/doc/readme.md)

---

_Exchanger is open to selected partnerships with exchange rate providers and financial infrastructure companies. For inquiries, contact the maintainer via [GitHub](https://github.com/florianv)._
