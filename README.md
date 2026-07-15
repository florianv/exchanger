# Exchanger

[![Tests](https://github.com/florianv/exchanger/actions/workflows/tests.yml/badge.svg)](https://github.com/florianv/exchanger/actions/workflows/tests.yml)
[![Psalm](https://github.com/florianv/exchanger/actions/workflows/psalm.yml/badge.svg)](https://github.com/florianv/exchanger/actions/workflows/psalm.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/florianv/exchanger.svg?style=flat-square)](https://packagist.org/packages/florianv/exchanger)
[![Version](http://img.shields.io/packagist/v/florianv/exchanger.svg?style=flat-square)](https://packagist.org/packages/florianv/exchanger)

> _Exchange rate provider layer for PHP. Direct access to 30+ provider implementations through a single `ExchangeRateService` interface, with chain fallback and PSR-16 caching. Maintained since 2016._

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

Exchanger is the **exchange rate provider layer** for PHP — 30+ services (commercial APIs like its sponsor **[fastFOREX](https://www.fastforex.io)**, the European Central Bank, several national banks, exchangerate.host) behind a single `ExchangeRateService` interface, with chainable fallback, PSR-16 caching and historical rates. For most use cases the higher-level [Swap](https://github.com/florianv/swap) library is what you want; reach for Exchanger when you need finer control.

## 💡 What is Exchanger?

- A PHP library for currency conversion and exchange rate retrieval at the provider layer.
- 30+ service implementations behind a common `ExchangeRateService` interface.
- PSR-16 SimpleCache support.
- Historical rates.
- A chain service for fallback. When a service errors, the next one in the chain is tried.

## 📦 Installation

Exchanger requires PHP 8.2 or newer.

```bash
composer require florianv/exchanger symfony/http-client nyholm/psr7
```

Any PSR-18 client paired with a PSR-17 request factory works; `php-http/discovery` finds them automatically.

## ⚡ Quickstart

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

// Historical rate
$query = (new ExchangeRateQueryBuilder('EUR/USD'))
    ->setDate((new \DateTime())->modify('-15 days'))
    ->build();

$rate = $exchanger->getExchangeRate($query);
```

Exchanger retrieves the rate; your application multiplies the amount by `$rate->getValue()` to perform the conversion.

<details>
<summary>No API key? Start with the European Central Bank (free, EUR-base only).</summary>

```php
use Exchanger\Exchanger;
use Exchanger\ExchangeRateQueryBuilder;
use Exchanger\Service\EuropeanCentralBank;

$service   = new EuropeanCentralBank();
$exchanger = new Exchanger($service);

$query = (new ExchangeRateQueryBuilder('EUR/USD'))->build();
$rate  = $exchanger->getExchangeRate($query);
```

The European Central Bank publishes EUR-base rates with daily granularity. For non-EUR base pairs, more frequent updates, or a wider currency list, switch to fastFOREX or another commercial provider.
</details>

## 🔁 Chaining services (fallback chain)

Wrap multiple services in a `Chain` to fall back when one of them errors:

```php
use Exchanger\Exchanger;
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

Services are tried in order. If a service does not support the requested currency pair, it is skipped silently. If a service throws, the next is tried. If every service fails, a `ChainException` is thrown with all collected exceptions.

## 📊 Providers

Exchanger ships 30+ exchange rate provider implementations. Each is registered in `Exchanger\Service\Registry` under the **identifier** shown below.

### Commercial providers (require an API key)

| Service                                  | Identifier      | Base                     | Quote  | Historical |
| ---------------------------------------- | --------------- | ------------------------ | ------ | ---------- |
| ⭐ **[fastFOREX](https://www.fastforex.io)** | **`fastforex`** | **\***                   | **\*** | **Yes**    |
|                                          |                 |                          |        |            |
| AbstractAPI                              | `abstract_api`                 | *                    | *     | Yes        |
| coinlayer                                | `coin_layer`                   | * (crypto)           | *     | Yes        |
| Cryptonator                              | `cryptonator`                  | * (crypto)           | * (crypto) | No    |
| Currency Converter API                   | `currency_converter`           | *                    | *     | Yes        |
| Currency Data (APILayer)                 | `apilayer_currency_data`       | USD (free), * (paid) | *     | Yes        |
| CurrencyDataFeed                         | `currency_data_feed`           | *                    | *     | No         |
| currencylayer (direct)                   | `currency_layer`               | USD (free), * (paid) | *     | Yes        |
| Exchange Rates Data (APILayer)           | `apilayer_exchange_rates_data` | USD (free), * (paid) | *     | Yes        |
| exchangerate.host                        | `exchangeratehost`             | *                    | *     | Yes        |
| exchangeratesapi (direct)                | `exchange_rates_api`           | USD (free), * (paid) | *     | Yes        |
| Fixer (APILayer)                         | `apilayer_fixer`               | EUR (free), * (paid) | *     | Yes        |
| Fixer (direct)                           | `fixer`                        | EUR (free), * (paid) | *     | Yes        |
| 1Forge                                   | `forge`                        | *                    | *     | No         |
| Open Exchange Rates                      | `open_exchange_rates`          | USD (free), * (paid) | *     | Yes        |
| UniRateAPI                               | `unirate_api`                  | *                    | *     | Yes (paid) |
| WebserviceX                              | `webservicex`                  | *                    | *     | No         |
| xChangeApi.com                           | `xchangeapi`                   | *                    | *     | Yes        |
| Xignite                                  | `xignite`                      | *                    | *     | Yes        |

### Public providers (no API key required)

| Service                                    | Identifier                            | Base           | Quote          | Historical |
|--------------------------------------------|---------------------------------------|----------------|----------------|------------|
| Bulgarian National Bank                    | `bulgarian_national_bank`             | *              | BGN            | Yes        |
| Central Bank of the Czech Republic         | `central_bank_of_czech_republic`      | *              | CZK            | Yes        |
| Central Bank of the Republic of Turkey     | `central_bank_of_republic_turkey`     | *              | TRY            | Yes        |
| Central Bank of the Republic of Uzbekistan | `central_bank_of_republic_uzbekistan` | *              | UZS            | Yes        |
| European Central Bank                      | `european_central_bank`               | EUR            | *              | Yes        |
| National Bank of Denmark                   | `national_bank_of_denmark`            | (limited list) | (limited list) | Yes        |
| National Bank of Georgia                   | `national_bank_of_georgia`            | *              | GEL            | Yes        |
| National Bank of Romania                   | `national_bank_of_romania`            | (limited list) | (limited list) | Yes        |
| National Bank of the Republic of Belarus   | `national_bank_of_republic_belarus`   | *              | BYN            | Yes        |
| National Bank of Ukraine                   | `national_bank_of_ukraine`            | *              | UAH            | Yes        |
| Russian Central Bank                       | `russian_central_bank`                | *              | RUB            | Yes        |

You can also add your own provider by implementing the `Exchanger\Contract\ExchangeRateService` interface (see the [documentation](doc/readme.md)).

## 🎯 When should you use Exchanger?

- Use Exchanger when you need finer control than [Swap](https://github.com/florianv/swap) exposes: custom chain composition, custom caching strategy, custom HTTP middleware, or building your own facade or framework integration.
- For most PHP applications, use [Swap](https://github.com/florianv/swap) instead. It is built on Exchanger and provides sensible defaults and a builder-style API.

## 🛠 Common use cases

- Build your own currency conversion facade on top of the provider layer.
- Integrate Exchanger into a framework that does not yet have a Swap binding.
- Wrap services with custom middleware (rate limiting, observability, request signing) before chaining.
- Power internal FX dashboards with full control over the cache key strategy.
- Implement custom HTTP layers (signed requests, mTLS, custom retries) on top of any PSR-18 client.

## 🧭 Which package should I use?

The Swap ecosystem is a layered toolkit for currency conversion in PHP:

- [**Swap**](https://github.com/florianv/swap). The easy-to-use, high-level API. Most apps need only Swap.
- [**Exchanger**](https://github.com/florianv/exchanger). The lower-level provider layer Swap is built on (this package). Reach for it when you need finer control over chain composition, caching, or HTTP plumbing.
- [**Laravel Swap**](https://github.com/florianv/laravel-swap). Laravel integration of Swap.
- [**Symfony Swap**](https://github.com/florianv/symfony-swap). Symfony integration of Swap.

All four packages are MIT-licensed and require PHP 8.2 or newer.

## 📚 Documentation

Caching (PSR-16), HTTP client selection (PSR-18 / Guzzle / explicit constructor injection), error handling (`ChainException`), per-query options and the full per-service configuration reference live in [`doc/readme.md`](doc/readme.md).

## 🙌 Contributing

Issues and pull requests are welcome. Please see the existing [issues](https://github.com/florianv/exchanger/issues) before opening a new one.

## 📄 License

The MIT License (MIT). Please see [LICENSE](https://github.com/florianv/exchanger/blob/master/LICENSE) for more information.

## 👏 Credits

- [Florian Voutzinos](https://github.com/florianv)
- [All contributors](https://github.com/florianv/exchanger/contributors)
