# Documentation

## 💡 What is Exchanger?

- Exchanger is a PHP library for currency conversion and exchange rate retrieval at the provider layer.
- It contains 30 service implementations behind a common `ExchangeRateService` interface.
- It caches results via PSR-16 SimpleCache.
- It supports historical rates.
- It supports a chain service for fallback. When a service errors, the next one in the chain is tried.

For the wider ecosystem (Swap, Laravel Swap, Symfony Swap), see the [README](../README.md).

## 🎯 When should you use Exchanger?

- Use Exchanger when you need finer control than [Swap](https://github.com/florianv/swap) exposes: custom chain composition, custom caching strategy, custom HTTP middleware, or building your own facade or framework integration.
- For most PHP applications, use [Swap](https://github.com/florianv/swap) instead. It is built on Exchanger and provides sensible defaults and a builder-style API.

## 🧠 Why Exchanger over Swap?

Swap is the easy-to-use, high-level API. Exchanger is the layer Swap is built on.

Reach for Exchanger directly when:

- **Custom facade:** you want to build your own currency conversion API on top of the provider layer.
- **Framework binding:** you are integrating into a framework that does not yet have a Swap binding.
- **Fine-grained chain composition:** you need to wrap services with custom logic before chaining.
- **Direct cache control:** you want to manage the PSR-16 cache strategy yourself.
- **Custom HTTP layer:** you need an HTTP middleware stack the Swap builder does not expose.

If none of these apply, use Swap.

## Index

* [Installation](#-installation)
* [Configuration](#-configuration)
  * [Building Exchanger](#building-exchanger)
  * [Chaining services](#chaining-services)
  * [How the chain works](#how-the-chain-works)
* [Usage](#-usage)
  * [Building queries](#building-queries)
  * [Latest and historical rates](#latest-and-historical-rates)
  * [Inspecting the rate](#inspecting-the-rate)
* [Caching](#-caching)
  * [PSR-16 SimpleCache (minimal setup)](#psr-16-simplecache-minimal-setup)
  * [Per-query options](#per-query-options)
  * [HTTP request caching](#http-request-caching)
* [Provider configuration](#-provider-configuration)
* [Creating a custom service](#-creating-a-custom-service)
  * [Standard service](#standard-service)
  * [Historical service](#historical-service)
* [FAQ](#-faq)

## 📦 Installation

Exchanger requires PHP 8.2 or newer. It does not bundle an HTTP client; any PSR-18 client paired with a PSR-17 request factory works, and `php-http/discovery` finds them automatically.

The simplest modern install:

```bash
composer require florianv/exchanger symfony/http-client nyholm/psr7
```

Other PSR-18 clients work the same way, for example Guzzle 7:

```bash
composer require florianv/exchanger php-http/guzzle7-adapter nyholm/psr7
```

You can also pass a client explicitly to each service constructor if you do not want auto-discovery.

## ⚙ Configuration

### Building Exchanger

`Exchanger` is constructed with a single service:

```php
use Exchanger\Exchanger;
use Exchanger\Service\EuropeanCentralBank;

$service   = new EuropeanCentralBank();
$exchanger = new Exchanger($service);
```

Service constructors take an HTTP client (any PSR-18 client) as the first argument and a PSR-17 request factory as the second; both can be left `null` to auto-discover. The third argument is the per-service options array. For example:

```php
use Exchanger\Service\OpenExchangeRates;

$service = new OpenExchangeRates(null, null, ['app_id' => 'YOUR_APP_ID']);
```

Per-service option keys are documented in the [Provider configuration](#-provider-configuration) section.

### Chaining services

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

### How the chain works

The `Chain` calls services in declaration order. For each service:

1. If the service does not support the requested currency pair, it is skipped silently.
2. If the service throws an exception, the exception is collected and the next service is tried.
3. If a service returns a rate, that rate is returned to the caller and the remaining services are not called.

If every service was skipped or threw, the chain raises an `Exchanger\Exception\ChainException` containing all collected exceptions. The chain does not retry the same service, and there is no built-in delay between attempts.

## ⚡ Usage

### Building queries

Exchanger uses a query object built with `ExchangeRateQueryBuilder`:

```php
use Exchanger\ExchangeRateQueryBuilder;

$query = (new ExchangeRateQueryBuilder('EUR/USD'))->build();
```

> Currencies are expressed as their [ISO 4217](http://en.wikipedia.org/wiki/ISO_4217) code.

### Latest and historical rates

```php
// Latest rate
$query = (new ExchangeRateQueryBuilder('EUR/USD'))->build();
$rate  = $exchanger->getExchangeRate($query);

echo $rate->getValue();                 // e.g. 1.0823
echo $rate->getDate()->format('Y-m-d'); // e.g. 2026-04-29

// Historical rate
$query = (new ExchangeRateQueryBuilder('EUR/USD'))
    ->setDate((new \DateTime())->modify('-15 days'))
    ->build();

$rate = $exchanger->getExchangeRate($query);
```

### Inspecting the rate

The returned `Exchanger\Contract\ExchangeRate` exposes:

```php
$rate->getValue();         // float
$rate->getDate();          // DateTimeInterface
$rate->getCurrencyPair();  // Exchanger\CurrencyPair
$rate->getProviderName();  // string, the identifier of the service that returned the rate
```

`getProviderName()` is useful when several services are chained: the returned value is the identifier of the service that actually answered, for example `european_central_bank`.

## 💾 Caching

### PSR-16 SimpleCache (minimal setup)

Exchanger caches results through a PSR-16 `SimpleCache` passed as the second constructor argument. Any PSR-16 implementation works. A minimal Symfony Cache example:

```bash
composer require symfony/cache
```

```php
use Exchanger\Exchanger;
use Exchanger\Service\EuropeanCentralBank;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

$cache = new Psr16Cache(new FilesystemAdapter());

$exchanger = new Exchanger(
    new EuropeanCentralBank(),
    $cache,
    ['cache_ttl' => 3600, 'cache_key_prefix' => 'myapp-']
);
```

If only PSR-6 adapters are available, you can bridge them to PSR-16 with [`cache/simple-cache-bridge`](https://github.com/php-cache/simple-cache-bridge).

### Per-query options

Cache behavior can be overridden per query via `addOption()` on the builder.

| Option             | Type   | Default | Effect                                                                                                |
| ------------------ | ------ | ------- | ----------------------------------------------------------------------------------------------------- |
| `cache_ttl`        | int    | `null`  | Cache TTL in seconds. `null` means entries do not expire.                                             |
| `cache`            | bool   | `true`  | Set to `false` to bypass the cache for this query.                                                    |
| `cache_key_prefix` | string | `""`    | Prefix for the cache key. Max 24 characters (PSR-6 limits keys to 64 chars; the internal hash takes 40). |

PSR-6 does not allow the characters `{}()/\@:` in keys; Exchanger replaces them with `-`.

```php
$query = (new ExchangeRateQueryBuilder('EUR/USD'))
    ->addOption('cache', false)
    ->build();

$query = (new ExchangeRateQueryBuilder('EUR/USD'))
    ->addOption('cache_ttl', 60)
    ->build();
```

### HTTP request caching

Some services return all rates for a given base currency in a single response. If you fetch several pairs sharing the same base, caching the underlying HTTP response avoids a second round trip. Decorate your HTTP client with the PHP HTTP cache plugin and pass it to the service constructor:

```bash
composer require php-http/cache-plugin cache/array-adapter
```

```php
use Cache\Adapter\PHPArray\ArrayCachePool;
use Exchanger\Exchanger;
use Exchanger\ExchangeRateQueryBuilder;
use Exchanger\Service\EuropeanCentralBank;
use Http\Adapter\Guzzle7\Client as GuzzleClient;
use Http\Client\Common\Plugin\CachePlugin;
use Http\Client\Common\PluginClient;
use Http\Message\StreamFactory\GuzzleStreamFactory;

$pool          = new ArrayCachePool();
$streamFactory = new GuzzleStreamFactory();
$cachePlugin   = new CachePlugin($pool, $streamFactory);
$httpClient    = new PluginClient(new GuzzleClient(), [$cachePlugin]);

$service   = new EuropeanCentralBank($httpClient);
$exchanger = new Exchanger($service);

$exchanger->getExchangeRate(
    (new ExchangeRateQueryBuilder('EUR/USD'))->build()
); // performs an HTTP request

$exchanger->getExchangeRate(
    (new ExchangeRateQueryBuilder('EUR/GBP'))->build()
); // hits the HTTP cache
```

## 🔑 Provider configuration

Public services need no configuration; instantiate them directly:

```php
use Exchanger\Service\EuropeanCentralBank;
use Exchanger\Service\NationalBankOfRomania;

$service = new EuropeanCentralBank();
$service = new NationalBankOfRomania();
```

Commercial services take an HTTP client, a request factory (both can be `null`), and an options array. The option key varies by service:

| Service class                                  | Required option | Optional flags        |
| ---------------------------------------------- | --------------- | --------------------- |
| `Exchanger\Service\AbstractApi`                | `api_key`       |                       |
| `Exchanger\Service\ApiLayer\CurrencyData`      | `api_key`       |                       |
| `Exchanger\Service\ApiLayer\ExchangeRatesData` | `api_key`       |                       |
| `Exchanger\Service\ApiLayer\Fixer`             | `api_key`       |                       |
| `Exchanger\Service\CoinLayer`                  | `access_key`    | `paid` (bool)         |
| `Exchanger\Service\CurrencyConverter`          | `access_key`    | `enterprise` (bool)   |
| `Exchanger\Service\CurrencyDataFeed`           | `api_key`       |                       |
| `Exchanger\Service\CurrencyLayer`              | `access_key`    | `enterprise` (bool)   |
| `Exchanger\Service\ExchangeRatesApi`           | `access_key`    |                       |
| `Exchanger\Service\FastForex`                  | `api_key`       |                       |
| `Exchanger\Service\Fixer`                      | `access_key`    |                       |
| `Exchanger\Service\FixerApiLayer`              | `api_key`       |                       |
| `Exchanger\Service\Forge`                      | `api_key`       |                       |
| `Exchanger\Service\OpenExchangeRates`          | `app_id`        | `enterprise` (bool)   |
| `Exchanger\Service\XchangeApi`                 | `api-key`       | (note the hyphen)     |
| `Exchanger\Service\Xignite`                    | `token`         |                       |

Example:

```php
use Exchanger\Service\ApiLayer\Fixer;
use Exchanger\Service\OpenExchangeRates;
use Exchanger\Service\Xignite;

$fixer   = new Fixer(null, null, ['api_key' => 'YOUR_KEY']);
$oer     = new OpenExchangeRates(null, null, ['app_id' => 'YOUR_APP_ID', 'enterprise' => false]);
$xignite = new Xignite(null, null, ['token' => 'YOUR_TOKEN']);
```

The `Exchanger\Service\PhpArray` service (registry identifier `array`) is a special case used in tests and fixtures. It accepts a structure of latest and historical rates:

```php
use Exchanger\Service\PhpArray;

$service = new PhpArray(
    ['EUR/USD' => 1.1, 'EUR/GBP' => 1.5],          // latest rates
    ['2017-01-01' => ['EUR/USD' => 1.5]]           // historical rates
);
```

The full provider list with capabilities (base currency, quote currency, historical support) is in the [README's Providers table](../README.md#-providers).

## 🧩 Creating a custom service

If your service makes HTTP requests, extend `Exchanger\Service\HttpService`; otherwise extend the simpler `Exchanger\Service\Service`.

### Standard service

The example below creates a `Constant` service that returns a fixed rate value:

```php
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\ExchangeRate;
use Exchanger\Exchanger;
use Exchanger\ExchangeRateQueryBuilder;
use Exchanger\Service\HttpService;

class ConstantService extends HttpService
{
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        // To call an HTTP endpoint:
        // $content = $this->request('https://example.com');

        return $this->createInstantRate($exchangeQuery->getCurrencyPair(), $this->options['value']);
    }

    public function processOptions(array &$options): void
    {
        if (!isset($options['value'])) {
            throw new \InvalidArgumentException('The "value" option must be provided.');
        }
    }

    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        // Example: only support EUR-base pairs.
        return 'EUR' === $exchangeQuery->getCurrencyPair()->getBaseCurrency();
    }

    public function getName(): string
    {
        return 'constant';
    }
}

$service   = new ConstantService(null, null, ['value' => 10]);
$exchanger = new Exchanger($service);

$query = (new ExchangeRateQueryBuilder('EUR/USD'))->build();
echo $exchanger->getExchangeRate($query)->getValue(); // 10
```

### Historical service

To support historical rates, use the `SupportsHistoricalQueries` trait. Rename `getExchangeRate` to `getLatestExchangeRate` (now `protected`) and implement `getHistoricalExchangeRate`:

```php
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\ExchangeRate;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\HttpService;
use Exchanger\Service\SupportsHistoricalQueries;

class ConstantService extends HttpService
{
    use SupportsHistoricalQueries;

    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        return $this->createInstantRate($exchangeQuery->getCurrencyPair(), $this->options['value']);
    }

    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        return $this->createInstantRate($exchangeQuery->getCurrencyPair(), $this->options['value']);
    }
}
```

## ❓ FAQ

#### What happens when every service in the chain fails?

The `Chain` throws an `Exchanger\Exception\ChainException`. Calling `$exception->getExceptions()` on it returns the list of exceptions collected from each service in the chain.

#### Can I use Exchanger without an API key?

Yes. The European Central Bank, the national banks, `Cryptonator`, `ExchangerateHost`, and `WebserviceX` do not require an API key. See the [Providers table](../README.md#-providers) for the full list.

#### How does Exchanger relate to Swap?

Swap is the high-level, easy-to-use API. Exchanger is the lower-level provider layer Swap is built on. Reach for Exchanger directly only when you need finer control over chain composition, caching, or HTTP plumbing. See the README's [Which package should I use?](../README.md#-which-package-should-i-use) section.

#### How do I cache rates?

Pass any PSR-16 cache as the second constructor argument: `new Exchanger($service, $cache)`. See [PSR-16 SimpleCache (minimal setup)](#psr-16-simplecache-minimal-setup).

#### How do I disable cache for a single query?

Add `->addOption('cache', false)` to the query builder.

#### How do I add my own service?

Implement `Exchanger\Contract\ExchangeRateService` (or extend `HttpService` / `Service`), then pass an instance to `Exchanger` directly or wrap it in a `Chain` with other services. See [Creating a custom service](#-creating-a-custom-service).

#### Where is the full provider list with capabilities?

In the [README's Providers table](../README.md#-providers). It lists every supported service with its base currency, quote currency, and historical support.
