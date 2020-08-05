# Documentation

## Index

* [Installation](#installation)
* [Configuration](#configuration)
* [Usage](#usage)
  * [Latest Rates](#latest-rates)
  * [Historical Rates](#historical-rates)
* [Chaining Services](#chaining-services)
    * [Rate Provider](#rate-provider)
* [Caching](#caching)
  * [Rates Caching](#rates-caching)
   * [Query Cache Options](#query-cache-options)
  * [Requests Caching](#requests-caching)
* [Creating a Service](#creating-a-service)
  * [Standard Service](#standard-service)
  * [Historical Service](#historical-service)
* [Supported Services](#supported-services)
* [Sponsors](#sponsors)

## Installation

Exchanger is decoupled from any library sending HTTP requests (like Guzzle), instead it uses an abstraction called [HTTPlug](http://httplug.io/) 
which provides the http layer used to send requests to exchange rate services. 
This gives you the flexibility to choose what HTTP client and PSR-7 implementation you want to use.

Read more about the benefits of this and about what different HTTP clients you may use in the [HTTPlug documentation](http://docs.php-http.org/en/latest/httplug/users.html). 
Below is an example using the curl client:

```bash
composer require php-http/curl-client nyholm/psr7 php-http/message florianv/exchanger
```

## Configuration

First, you need to create a **service** and add it to `Exchanger`.

We recommend to use one of the [services that support our project](#sponsors), providing a free plan up to 1,000 requests per day.

The complete list of all supported services is available [here](https://github.com/florianv/exchanger/blob/master/README.md#services).

```php
use Http\Client\Curl\Client as CurlClient;
use Exchanger\Service\Fixer;
use Exchanger\Service\CurrencyLayer;
use Exchanger\Exchanger;

// Create your http client (we choose curl here)
$client = new CurlClient();

// Use the Fixer.io service
$service = new Fixer($client, null, ['access_key' => 'YOUR_KEY']);

// Or use the currencylayer.com service
$service = new CurrencyLayer($client, null, ['access_key' => 'access_key', 'enterprise' => false]);

// Create Exchanger with your service
$exchanger = new Exchanger($service);
```

### Usage

#### Latest Rates

`Exchanger` uses a concept of queries. In order to get an exchange rate, you need to build a **query** and `Exchanger` will process it to return the rate. 
The example below shows how to get the **latest** `EUR/USD` exchange rate.

```php
use Exchanger\ExchangeRateQueryBuilder;

// Create the query to get the latest EUR/USD rate
$query = (new ExchangeRateQueryBuilder('EUR/USD'))
    ->build();

// Get the exchange rate
$rate = $exchanger->getExchangeRate($query);

// 1.1159
echo $rate->getValue();

// 2016-09-06
echo $rate->getDate()->format('Y-m-d');
```

> Currencies are expressed as their [ISO 4217](http://en.wikipedia.org/wiki/ISO_4217) code.

#### Historical Rates

`Exchanger` allows you to retrieve **historical** exchange rates but not all services support this feature as you can see in this [table](https://github.com/florianv/exchanger/blob/master/README.md#services).

```php
// Create the query to get the EUR/USD rate 15 days ago
$query = (new ExchangeRateQueryBuilder('EUR/USD'))
    ->setDate((new \DateTime())->modify('-15 days'))
    ->build();

// Get the exchange rate
$rate = $exchanger->getExchangeRate($query);

// 1.1339
echo $rate->getValue();

// 2016-08-23
echo $rate->getDate()->format('Y-m-d');
```

### Chaining Services

It is possible to chain services in order to use fallbacks in case the previous ones don't support the currency or are unavailable.
Simply create a `Chain` service to wrap the services you want to chain.

```php
use Exchanger\Service\Chain;
use Exchanger\Service\Fixer;
use Exchanger\Service\CurrencyLayer;

$service = new Chain([
    new Fixer($client, null, ['access_key' => 'YOUR_KEY']),
    new CurrencyLayer($client, null, ['access_key' => 'access_key', 'enterprise' => false]),
]);
```

The rates will be first fetched using the `Fixer` service, will fallback to `CurrencyLayer`.

> You can consult the list of the supported services and their options [here](#supported-services)

#### Rate provider

When using the chain service, it can be useful to know which service provided the rate.

You can use the `getProviderName()` function on a rate that gives you the name of the service that returned it:

```php
$name = $rate->getProviderName();
```

For example, if Fixer returned the rate, it will be identical to `fixer`.

### Caching

#### Rates Caching

`Exchanger` provides a [PSR-16 Simple Cache](http://www.php-fig.org/psr/psr-16) integration allowing you to cache rates during a given time using the adapter of your choice.

The following example uses the `Predis` cache from [php-cache.com](http://php-cache.com) PSR-6 implementation installable using `composer require cache/predis-adapter`.

You will also need to install a "bridge" that allows to adapt the PSR-6 adapters to PSR-16 using `composer require cache/simple-cache-bridge` (https://github.com/php-cache/simple-cache-bridge).

```php
use Cache\Adapter\Predis\PredisCachePool;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;

$client = new \Predis\Client('tcp:/127.0.0.1:6379');
$psr6pool = new PredisCachePool($client);
$simpleCache = new SimpleCacheBridge($psr6pool);

$exchanger = new Exchanger($service, $simpleCache, ['cache_ttl' => 3600, 'cache_key_prefix' => 'myapp-']);
```

All rates will now be cached in Redis during 3600 seconds, and cache keys will be prefixed with 'myapp-'

##### Query Cache Options

For more control, you can configure the cache per query.

###### cache_ttl

Set cache TTL in seconds. Default: `null` - cache entries permanently

```php
// Override the global cache_ttl only for this query
$query = (new ExchangeRateQueryBuilder('JPY/GBP'))
    ->addOption('cache_ttl', 60)
    ->build();
```

###### cache

Disable/Enable caching. Default: `true`

```php
// Disable caching for this query
$query = (new ExchangeRateQueryBuilder('JPY/GBP'))
    ->addOption('cache', false)
    ->build();
```

###### cache_key_prefix

Set the cache key prefix. Default: empty string

There is a limitation of 64 characters for the key length in PSR-6, because of this, key prefix must not exceed 24 characters, as sha1() hash takes 40 symbols.

PSR-6 do not allows characters `{}()/\@:` in key, these characters are replaced with `-`

```php
// Override cache key prefix for this query
$query = (new ExchangeRateQueryBuilder('JPY/GBP'))
    ->addOption('cache_key_prefix', 'currencies-special-')
    ->build();
```    

#### Requests Caching

By default, `Exchanger` queries the service for each rate you request, but some services like the `EuropeanCentralBank`
return the same response no matter the requested currency pair. It means performances can be improved when using these services
and when quoting multiple pairs during the same request.

Install the PHP HTTP Cache plugin and the PHP Cache Array adapter `composer require php-http/cache-plugin cache/array-adapter`.

Modify the way you create your HTTP Client by decorating it with a `PluginClient` using the `Array` cache:

```php
use Http\Client\Common\PluginClient;
use Http\Client\Common\Plugin\CachePlugin;
use Http\Message\StreamFactory\GuzzleStreamFactory;
use Http\Adapter\Guzzle6\Client as GuzzleClient;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Exchanger\Service\EuropeanCentralBank;
use Exchanger\ExchangeRateQueryBuilder;
use Exchanger\Exchanger;

$pool = new ArrayCachePool();
$streamFactory = new GuzzleStreamFactory();
$cachePlugin = new CachePlugin($pool, $streamFactory);
$httpAdapter = new PluginClient(new GuzzleClient(), [$cachePlugin]);

$service = new EuropeanCentralBank($httpAdapter);

$exchanger = new Exchanger($service);

$query = (new ExchangeRateQueryBuilder('EUR/USD'))->build();

// An http request will be sent
$rate = $exchanger->getExchangeRate((new ExchangeRateQueryBuilder('EUR/USD'))->build());

// A new request won't be sent
$rate = $exchanger->getExchangeRate((new ExchangeRateQueryBuilder('EUR/GBP'))->build());
```

### Creating a Service

If your service must send http requests to retrieve rates, your class must extend the `HttpService` class, otherwise you can extend the more generic `Service` class.

#### Standard service

In the following example, we are creating a `Constant` service that returns a configurable constant rate value.

```php
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\ExchangeRate;
use Exchanger\Service\HttpService;

class ConstantService extends HttpService
{
    /**
     * Gets the exchange rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        // If you want to make a request you can use
        // $content = $this->request('http://example.com');

        return $this->createInstantRate($exchangeQuery->getCurrencyPair(), $this->options['value']);
    }

    /**
     * Processes the service options.
     *
     * @param array &$options
     *
     * @return void
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options['value'])) {
            throw new \InvalidArgumentException('The "value" option must be provided.');
        }
    }

    /**
     * Tells if the service supports the exchange rate query.
     *
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return bool
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        // For example, our service only supports EUR as base currency
        return 'EUR' === $exchangeQuery->getCurrencyPair()->getBaseCurrency();
    }

    /**
     * Gets the name of the exchange rate service.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'constant';
    }
}

$service = new ConstantService(null, null, ['value' => 10]);
$exchanger = new Exchanger($service);

$query = (new ExchangeRateQueryBuilder('EUR/USD'))->build();

// 10
$rate = $exchanger->getExchangeRate($query)->getValue();
```

#### Historical service

If your service supports retrieving historical rates, you need to use the `SupportsHistoricalQueries` trait.

You will need to rename the `getExchangeRate` method to `getLatestExchangeRate` and switch its visibility to protected, and implement a new `getHistoricalExchangeRate` method:

```php
use Exchanger\Service\SupportsHistoricalQueries;

class ConstantService extends HttpService
{
    use SupportsHistoricalQueries;
    
    /**
     * Gets the exchange rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        return $this->createInstantRate($exchangeQuery->getCurrencyPair(), $this->options['value']);
    }

    /**
     * Gets an historical rate.
     *
     * @param HistoricalExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        return $this->createInstantRate($exchangeQuery->getCurrencyPair(), $this->options['value']);
    }
}    
```

### Supported Services

Here is the complete list of supported services and their possible configurations:

```php
use Exchanger\Service\Chain;
use Exchanger\Service\Fixer;
use Exchanger\Service\CurrencyLayer;
use Exchanger\Service\CentralBankOfCzechRepublic;
use Exchanger\Service\CentralBankOfRepublicTurkey;
use Exchanger\Service\CurrencyDataFeed;
use Exchanger\Service\EuropeanCentralBank;
use Exchanger\Service\ExchangeRatesApi;
use Exchanger\Service\NationalBankOfRomania;
use Exchanger\Service\OpenExchangeRates;
use Exchanger\Service\PhpArray;
use Exchanger\Service\Forge;
use Exchanger\Service\WebserviceX;
use Exchanger\Service\Xignite;
use Exchanger\Service\RussianCentralBank;
use Exchanger\Service\Cryptonator;
use Exchanger\Service\CoinLayer;
use Exchanger\Service\XchangeApi;

$service = new Chain([
    new Fixer($client, null, ['access_key' => 'YOUR_KEY']),
    new CurrencyLayer($client, null, ['access_key' => 'access_key', 'enterprise' => false]),
    new CoinLayer($client, null, ['access_key' => 'access_key', 'paid' => false]),
    new EuropeanCentralBank(),
    new ExchangeRatesApi(),
    new NationalBankOfRomania(),
    new CentralBankOfRepublicTurkey(),
    new CentralBankOfCzechRepublic(),
    new RussianCentralBank(),
    new Forge($client, null, ['api_key' => 'api_key']),
    new WebserviceX(),
    new Cryptonator(),
    new CurrencyDataFeed($client, null, ['api_key' => 'api_key']),
    new OpenExchangeRates($client, null, ['app_id' => 'app_id', 'enterprise' => false]),
    new Xignite($client, null, ['token' => 'token']),
    new PhpArray(
        [
            'EUR/USD' => 1.1,
            'EUR/GBP' => 1.5
        ],
        [
            '2017-01-01' => [
                'EUR/USD' => 1.5
            ],
            '2017-01-03' => [
                'EUR/GBP' => 1.3
            ],
        ]
    ),
    new XchangeApi($client, null, ['api-key' => 'YOUR_KEY']),
]);
```

### Sponsors

We are proudly supported by the following echange rate providers offering *free plans up to 1,000 requests per day*:

<img src="https://s3.amazonaws.com/swap.assets/fixer_icon.png?v=2" height="20px" width="20px"/> **[Fixer](https://fixer.io)**

Fixer is a simple and lightweight API for foreign exchange rates that supports up to 170 world currencies.
They provide real-time rates and historical data, however, EUR is the only available base currency on the free plan.

<img src="https://s3.amazonaws.com/swap.assets/currencylayer_icon.png" height="20px" width="20px"/> **[currencylayer](https://currencylayer.com)**

Currencylayer provides reliable exchange rates and currency conversions for your business up to 168 world currencies.
They provide real-time rates and historical data, however, USD is the only available base currency on the free plan.
