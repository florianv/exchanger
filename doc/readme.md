# Documentation

## Index

* [Installation](#installation)
* [Configuration](#configuration)
* [Usage](#usage)
  * [Latest Rates](#latest-rates)
  * [Historical Rates](#historical-rates)
* [Chaining Services](#chaining-services)
* [Caching](#caching)
  * [Rates Caching](#rates-caching)
    * [Query Cache Options](#query-cache-options)
  * [Requests Caching](#requests-caching)
* [Creating a Service](#creating-a-service)
* [Supported Services](#supported-services)

## Installation

Exchanger is decoupled from any library sending HTTP requests (like Guzzle), instead it uses an abstraction called [HTTPlug](http://httplug.io/) 
which provides the http layer used to send requests to exchange rate services. 
This gives you the flexibility to choose what HTTP client and PSR-7 implementation you want to use.

Read more about the benefits of this and about what different HTTP clients you may use in the [HTTPlug documentation](http://docs.php-http.org/en/latest/httplug/users.html). 
Below is an example using [Guzzle 6](http://docs.guzzlephp.org/en/latest/index.html):

```bash
composer require florianv/exchanger php-http/message php-http/guzzle6-adapter
```

## Configuration

First, you need to create a **service** and add it to `Exchanger`. 

> You can consult the list of supported services [here](https://github.com/florianv/exchanger/blob/master/README.md#services).

```php
use Http\Adapter\Guzzle6\Client as GuzzleClient;
use Exchanger\Service\Fixer;
use Exchanger\Exchanger;

// Create your http client (we choose Guzzle 6 here)
$client = new GuzzleClient();

// Create the Fixer.io service
$service = new Fixer($client);

// Create Exchanger with the Fixer.io service
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
use Exchanger\Service\Yahoo;

$service = new Chain([
    new Fixer($client),
    new Yahoo($client)
]);
```

The rates will be first fetched using the `Fixer` service and will fallback to `Yahoo`.

> You can consult the list of the supported services and their options [here](#supported-services)

### Caching

#### Rates Caching

`Exchanger` provides a [PSR-6 Caching Interface](http://www.php-fig.org/psr/psr-6) integration allowing you to cache rates during a given time using the adapter of your choice.

The following example uses the Apcu cache from [php-cache.com](http://php-cache.com) PSR-6 implementation installable using `composer require cache/apcu-adapter`.

```php
use Cache\Adapter\Apcu\ApcuCachePool;

$exchanger = new Exchanger($service, new ApcuCachePool(), ['cache_ttl' => 3600]);
```

All rates will now be cached in Apcu during 3600 seconds.

##### Query Cache Options

For more control, you can configure the cache per query.

```php
// Override the global cache_ttl only for this query
$query = (new ExchangeRateQueryBuilder('JPY/GBP'))
    ->addOption('cache_ttl', 60)
    ->build();
    
// Disable caching for this query
$query = (new ExchangeRateQueryBuilder('JPY/GBP'))
    ->addOption('cache', false)
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

First you must check if the service supports retrieval of historical rates. If it's the case, you must extend the `HistoricalService` class,
otherwise use the `Service` class.

In the following example, we are creating a `Constant` service that returns a constant rate value.

```php
use Exchanger\Service\Service;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\ExchangeRate;

class ConstantService extends Service
{
    /**
     * Gets the exchange rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        // If you want to make a request you can use
        $content = $this->request('http://example.com');

        return new ExchangeRate($this->options['value']);
    }

    /**
     * Processes the service options.
     *
     * @param array &$options
     *
     * @return array
     */
    public function processOptions(array &$options)
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
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        // For example, our service only supports EUR as base currency
        return 'EUR' === $exchangeQuery->getCurrencyPair()->getBaseCurrency();
    }
}

$service = new ConstantService(null, null, ['value' => 10]);
$exchanger = new Exchanger($service);

$query = (new ExchangeRateQueryBuilder('EUR/USD'))->build();

// 10
$rate = $exchanger->getExchangeRate($query)->getValue();
```

### Supported Services

Here is the complete list of supported services and their possible configurations:

```php
use Exchanger\Service\OneForge;
use Exchanger\Service\Fixer;
use Exchanger\Service\Chain;
use Exchanger\Service\CentralBankOfCzechRepublic;
use Exchanger\Service\CentralBankOfRepublicTurkey;
use Exchanger\Service\CurrencyLayer;
use Exchanger\Service\EuropeanCentralBank;
use Exchanger\Service\Google;
use Exchanger\Service\NationalBankOfRomania;
use Exchanger\Service\OpenExchangeRates;
use Exchanger\Service\PhpArray;
use Exchanger\Service\WebserviceX;
use Exchanger\Service\Xignite;
use Exchanger\Service\Yahoo;
use Exchanger\Service\RussianCentralBank;

$service = new Chain([
    new OneForge(),
    new CentralBankOfCzechRepublic(),
    new CentralBankOfRepublicTurkey(),
    new CurrencyLayer($client, null, ['access_key' => 'access_key', 'enterprise' => false]),
    new EuropeanCentralBank(),
    new Fixer(),
    new Google(),
    new NationalBankOfRomania(),
    new OpenExchangeRates($client, null, ['app_id' => 'app_id', 'enterprise' => false]),
    new PhpArray(['EUR/USD' => new ExchangeRate('1.5')]),
    new WebserviceX(),
    new Xignite($client, null, ['token' => 'token']),
    new Yahoo()
    new RussianCentralBank()
]);
```
