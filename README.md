# Exchanger

[![Tests](https://github.com/florianv/exchanger/actions/workflows/tests.yml/badge.svg)](https://github.com/florianv/exchanger/actions/workflows/tests.yml)
[![Psalm](https://github.com/florianv/exchanger/actions/workflows/psalm.yml/badge.svg)](https://github.com/florianv/exchanger/actions/workflows/psalm.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/florianv/exchanger.svg?style=flat-square)](https://packagist.org/packages/florianv/exchanger)
[![Version](http://img.shields.io/packagist/v/florianv/exchanger.svg?style=flat-square)](https://packagist.org/packages/florianv/exchanger)

> _Exchange rate provider layer for PHP. Direct access to 30 provider implementations through a single `ExchangeRateService` interface, with chain fallback and PSR-16 caching. Maintained since 2016._

Exchanger is the **exchange rate provider layer** for PHP. It exposes 30 services (the European Central Bank, several national banks, exchangerate.host, and commercial **exchange rate APIs** that require an API key) behind a single `ExchangeRateService` interface, with chainable fallback, PSR-16 caching, and historical rates. Used in real-world PHP applications since 2016.

For most use cases, the higher-level [Swap](https://github.com/florianv/swap) library is what you want. Reach for Exchanger directly when you need finer control.

## 💡 What is Exchanger?

- Exchanger is a PHP library for currency conversion and exchange rate retrieval at the provider layer.
- It contains 30 service implementations behind a common `ExchangeRateService` interface.
- It caches results via PSR-16 SimpleCache.
- It supports historical rates.
- It supports a chain service for fallback. When a service errors, the next one in the chain is tried.

## 🎯 When should you use Exchanger?

- Use Exchanger when you need finer control than [Swap](https://github.com/florianv/swap) exposes: custom chain composition, custom caching strategy, custom HTTP middleware, or building your own facade or framework integration.
- For most PHP applications, use [Swap](https://github.com/florianv/swap) instead. It is built on Exchanger and provides sensible defaults and a builder-style API.

## 🧠 Why Exchanger over Swap?

Swap is the easy-to-use, high-level API. Exchanger is the layer Swap is built on.

Reach for Exchanger directly when:

- **Custom facade:** you want to build your own currency conversion API on top of the provider layer.
- **Framework binding:** you are integrating into a framework that does not yet have a Swap binding.
- **Fine-grained chain composition:** you need to wrap services with custom logic (rate limiting, observability, conditional fallback) before chaining them.
- **Direct cache control:** you want to manage the PSR-16 cache key strategy yourself.
- **Custom HTTP layer:** you need an HTTP middleware stack the Swap builder does not expose.

If none of these apply, use Swap.

## 📦 Installation

Exchanger requires PHP 8.2 or newer.

```bash
composer require florianv/exchanger symfony/http-client nyholm/psr7
```

Any PSR-18 client paired with a PSR-17 request factory works; `php-http/discovery` finds them automatically.

## ⚡ Quickstart

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

## 🔁 Chaining services (fallback chain)

Wrap multiple services in a `Chain` to fall back when one of them errors:

```php
use Exchanger\Exchanger;
use Exchanger\Service\Chain;
use Exchanger\Service\EuropeanCentralBank;

$service = new Chain([
    new YourPrimaryService(null, null, ['api_key' => 'YOUR_KEY']),
    new YourFallbackService(null, null, ['api_key' => 'YOUR_KEY']),
    new EuropeanCentralBank(), // free fallback for EUR-base pairs
]);

$exchanger = new Exchanger($service);
```

Services are tried in order. If a service does not support the requested currency pair, it is skipped silently. If a service throws an exception, the exception is collected and the next service is tried. If every service was skipped or threw, the chain raises an `Exchanger\Exception\ChainException` containing all collected exceptions.

## 🛠 Common use cases

- Build your own currency conversion facade on top of the provider layer.
- Integrate Exchanger into a framework that does not yet have a Swap binding.
- Wrap services with custom middleware (rate limiting, observability, request signing) before chaining.
- Power internal FX dashboards with full control over the cache key strategy.
- Implement custom HTTP layers (signed requests, mTLS, custom retries) on top of any PSR-18 client.

## 🧭 Which package should I use?

The Swap ecosystem is a layered toolkit for currency conversion in PHP:

- **Swap.** The easy-to-use, high-level API. Most apps need only Swap.
- **Exchanger.** The lower-level, more granular layer Swap is built on (this package). Reach for it when you need finer control over chain composition, caching, or HTTP plumbing.
- **Laravel Swap.** Laravel application of Swap.
- **Symfony Swap.** Symfony integration of Swap.

All four packages are MIT-licensed and require PHP 8.2 or newer.

## 📊 Providers

Exchanger ships 30 exchange rate provider implementations. Each is registered in `Exchanger\Service\Registry` under the **identifier** shown in the table.

### Public providers (no API key required)

| Service                                    | Identifier                            | Base           | Quote          | Historical |
| ------------------------------------------ | ------------------------------------- | -------------- | -------------- | ---------- |
| Bulgarian National Bank                    | `bulgarian_national_bank`             | *              | BGN            | Yes        |
| Central Bank of the Czech Republic         | `central_bank_of_czech_republic`      | *              | CZK            | Yes        |
| Central Bank of the Republic of Turkey     | `central_bank_of_republic_turkey`     | *              | TRY            | Yes        |
| Central Bank of the Republic of Uzbekistan | `central_bank_of_republic_uzbekistan` | *              | UZS            | Yes        |
| Cryptonator                                | `cryptonator`                         | * (crypto)     | * (crypto)     | No         |
| European Central Bank                      | `european_central_bank`               | EUR            | *              | Yes        |
| exchangerate.host                          | `exchangeratehost`                    | *              | *              | Yes        |
| National Bank of Georgia                   | `national_bank_of_georgia`            | *              | GEL            | Yes        |
| National Bank of Romania                   | `national_bank_of_romania`            | (limited list) | (limited list) | Yes        |
| National Bank of the Republic of Belarus   | `national_bank_of_republic_belarus`   | *              | BYN            | Yes        |
| National Bank of Ukraine                   | `national_bank_of_ukraine`            | *              | UAH            | Yes        |
| Russian Central Bank                       | `russian_central_bank`                | *              | RUB            | Yes        |
| WebserviceX                                | `webservicex`                         | *              | *              | No         |

### Commercial providers (require an API key)

| Service                          | Identifier                       | Base                  | Quote | Historical |
| -------------------------------- | -------------------------------- | --------------------- | ----- | ---------- |
| AbstractAPI                      | `abstract_api`                   | *                     | *     | Yes        |
| coinlayer                        | `coin_layer`                     | * (crypto)            | *     | Yes        |
| Currency Converter API           | `currency_converter`             | *                     | *     | Yes        |
| Currency Data (APILayer)         | `apilayer_currency_data`         | USD (free), * (paid)  | *     | Yes        |
| CurrencyDataFeed                 | `currency_data_feed`             | *                     | *     | No         |
| currencylayer (direct)           | `currency_layer`                 | USD (free), * (paid)  | *     | Yes        |
| Exchange Rates Data (APILayer)   | `apilayer_exchange_rates_data`   | USD (free), * (paid)  | *     | Yes        |
| exchangeratesapi (direct)        | `exchange_rates_api`             | USD (free), * (paid)  | *     | Yes        |
| fastFOREX.io                     | `fastforex`                      | *                     | *     | Yes        |
| Fixer (APILayer)                 | `apilayer_fixer`                 | EUR (free), * (paid)  | *     | Yes        |
| Fixer (direct)                   | `fixer`                          | EUR (free), * (paid)  | *     | Yes        |
| 1Forge                           | `forge`                          | *                     | *     | No         |
| Open Exchange Rates              | `open_exchange_rates`            | USD (free), * (paid)  | *     | Yes        |
| xChangeApi.com                   | `xchangeapi`                     | *                     | *     | Yes        |
| Xignite                          | `xignite`                        | *                     | *     | Yes        |

You can also add your own provider by implementing the `Exchanger\Contract\ExchangeRateService` interface (see the [documentation](doc/readme.md)).

## ⚙ Caching, HTTP client, and error handling

- **Caching:** PSR-16 `SimpleCache` is passed as the second constructor argument: `new Exchanger($service, $cache)`. Per-query disable: `->addOption('cache', false)`. Per-query TTL: `->addOption('cache_ttl', 3600)`.
- **HTTP client:** any PSR-18 client (`symfony/http-client`, `php-http/guzzle7-adapter`, etc.). Pass it explicitly to each service constructor, or rely on `php-http/discovery` to auto-discover.
- **Errors:** when every service in a `Chain` has either skipped (unsupported pair) or thrown, the chain raises an `Exchanger\Exception\ChainException` containing all collected exceptions.

## 📚 Documentation

The full documentation, with the per-provider configuration reference, caching options, and how to write your own service, is in [doc/readme.md](doc/readme.md).

## 🧩 Related packages

The Swap ecosystem:

- [**Swap**](https://github.com/florianv/swap): easy-to-use PHP currency conversion library.
- [**Exchanger**](https://github.com/florianv/exchanger): exchange rate provider layer (this package).
- [**Laravel Swap**](https://github.com/florianv/laravel-swap): Laravel application of Swap.
- [**Symfony Swap**](https://github.com/florianv/symfony-swap): Symfony integration of Swap.

## 🤝 Sponsorship

The Swap ecosystem is open to selected sponsorships from exchange rate API providers and financial infrastructure companies.

Sponsorship can include:

- Documentation visibility
- Integration examples
- Ecosystem-level visibility across Swap, Exchanger, Laravel Swap, and Symfony Swap

For inquiries, contact the maintainer via [GitHub](https://github.com/florianv).

## 🙌 Contributing

Issues and pull requests are welcome. Please see the existing [issues](https://github.com/florianv/exchanger/issues) before opening a new one.

## 📄 License

The MIT License (MIT). Please see [LICENSE](https://github.com/florianv/exchanger/blob/master/LICENSE) for more information.

## 👏 Credits

- [Florian Voutzinos](https://github.com/florianv)
- [All contributors](https://github.com/florianv/exchanger/contributors)
