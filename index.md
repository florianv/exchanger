---
title: "Exchanger: PHP exchange rate provider layer"
description: PHP exchange rate provider layer for currency conversion. 30 services, chain fallback, and caching. Maintained since 2016.
---

**Exchange rate provider layer for PHP, with chain fallback, caching, and direct access to 30 implementations.**

Most exchange rate APIs are a single point of failure. Exchanger gives you a single interface over 30 providers, with chainable fallback and PSR-16 caching.

> Used in production PHP applications since 2016.

Exchanger is the PHP **exchange rate provider layer**. It exposes 30 services (the European Central Bank, several national banks, exchangerate.host, and commercial **exchange rate APIs** that require an API key) behind a single `ExchangeRateService` interface, with chainable fallback, PSR-16 caching, and historical rates.

For most use cases, the higher-level [Swap](https://github.com/florianv/swap) library is what you want. Reach for Exchanger directly when you need finer control.

## What is Exchanger?

- Exchanger is a PHP library for currency conversion and exchange rate retrieval at the provider layer.
- It contains 30 service implementations behind a common `ExchangeRateService` interface.
- It caches results via PSR-16 SimpleCache.
- It supports historical rates.
- It supports a chain service for fallback. When a service errors, the next one in the chain is tried.

## When should you use Exchanger?

- Use Exchanger when you need finer control than [Swap](https://github.com/florianv/swap) exposes: custom chain composition, custom caching strategy, custom HTTP middleware, or building your own facade or framework integration.
- For most PHP applications, use [Swap](https://github.com/florianv/swap) instead. It is built on Exchanger and provides sensible defaults and a builder-style API.

## Why Exchanger over Swap?

Swap is the easy-to-use, high-level API. Exchanger is the layer Swap is built on.

Reach for Exchanger directly when:

- **Custom facade:** you want to build your own currency conversion API on top of the provider layer.
- **Framework binding:** you are integrating into a framework that does not yet have a Swap binding.
- **Fine-grained chain composition:** you need to wrap services with custom logic before chaining them.
- **Direct cache control:** you want to manage the PSR-16 cache strategy yourself.
- **Custom HTTP layer:** you need an HTTP middleware stack the Swap builder does not expose.

If none of these apply, use Swap.

## Quickstart

Exchanger requires PHP 8.2 or newer.

Install via Composer:

```bash
composer require florianv/exchanger symfony/http-client nyholm/psr7
```

Use it:

```php
use Exchanger\Exchanger;
use Exchanger\ExchangeRateQueryBuilder;
use Exchanger\Service\EuropeanCentralBank;

// The European Central Bank is free, no API key required.
$service   = new EuropeanCentralBank();
$exchanger = new Exchanger($service);

// EUR → USD exchange rate
$query = (new ExchangeRateQueryBuilder('EUR/USD'))->build();
$rate  = $exchanger->getExchangeRate($query);

$rate->getValue();                 // e.g. 1.0823 (a float)
$rate->getDate()->format('Y-m-d'); // e.g. 2026-04-29
$rate->getProviderName();          // 'european_central_bank'

// Convert an amount using the returned rate
$amountInEUR = 100.00;
$amountInUSD = $amountInEUR * $rate->getValue();

// Historical rate
$query = (new ExchangeRateQueryBuilder('EUR/USD'))
    ->setDate((new \DateTime())->modify('-15 days'))
    ->build();

$rate = $exchanger->getExchangeRate($query);
```

Exchanger retrieves the rate; your application multiplies the amount by `$rate->getValue()` to perform the conversion.

## View on GitHub

Source code, full documentation, providers list, and issue tracker:

**[→ View on GitHub](https://github.com/florianv/exchanger)**

## Related packages

- [Swap](https://github.com/florianv/swap): easy-to-use PHP currency conversion library.
- [Exchanger](https://github.com/florianv/exchanger): exchange rate provider layer (this package).
- [Laravel Swap](https://github.com/florianv/laravel-swap): Laravel application of Swap.
- [Symfony Swap](https://github.com/florianv/symfony-swap): Symfony integration of Swap.

## Documentation

The full documentation, with the per-service configuration reference, caching options, and how to write your own service, is in [doc/readme.md](https://github.com/florianv/exchanger/blob/master/doc/readme.md) on the GitHub repository.

---

_Exchanger is open to selected partnerships with exchange rate providers._
