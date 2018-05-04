# Exchanger

[![Build status](http://img.shields.io/travis/florianv/exchanger/master.svg?style=flat-square)](https://travis-ci.org/florianv/exchanger)
[![Total Downloads](https://img.shields.io/packagist/dt/florianv/exchanger.svg?style=flat-square)](https://packagist.org/packages/florianv/exchanger)
[![Scrutinizer](https://img.shields.io/scrutinizer/g/florianv/exchanger.svg?style=flat-square)](https://scrutinizer-ci.com/g/florianv/exchanger)
[![Version](http://img.shields.io/packagist/v/florianv/exchanger.svg?style=flat-square)](https://packagist.org/packages/florianv/exchanger)

Exchanger is a PHP framework to work with currency exchange rates from various services such as 
**[Fixer](https://fixer.io)**, **[currencylayer](https://currencylayer.com)** or **[1Forge](https://1forge.com)**. 
Looking for a simple library based on Exchanger ? Check out [Swap](https://github.com/florianv/swap) !

## Sponsors :heart_eyes: 

We are proudly supported by the following echange rate providers offering *free plans up to 1,000 requests per day*:

<img src="https://s3.amazonaws.com/swap.assets/fixer_icon.png?v=2" height="20px" width="20px"/> **[Fixer](https://fixer.io)**

Fixer is a simple and lightweight API for foreign exchange rates that supports up to 170 world currencies.
They provide real-time rates and historical data, however, EUR is the only available base currency on the free plan.

<img src="https://s3.amazonaws.com/swap.assets/currencylayer_icon.png" height="20px" width="20px"/> **[currencylayer](https://currencylayer.com)**

Currencylayer provides reliable exchange rates and currency conversions for your business up to 168 world currencies.
They provide real-time rates and historical data, however, USD is the only available base currency on the free plan.

<img src="https://s3.amazonaws.com/swap.assets/1forge_icon.png" height="20px" width="20px"/> **[1Forge](https://1forge.com)**

1Forge provides Forex and Cryptocurrency quotes for over 700 unique currency pairs. 
They provide the fastest price updates available of any provider, however, they don’t support smaller currencies or historical data.

## Documentation

The documentation for the current branch can be found [here](https://github.com/florianv/exchanger/blob/master/doc/readme.md).

## Services

Here is the complete list of the currently implemented services:

| Service | Base Currency | Quote Currency | Historical |
|---------------------------------------------------------------------------|----------------------|----------------|----------------|
| [Fixer](https://fixer.io) | EUR (free, no SSL), * (paid) | * | Yes |
| [currencylayer](https://currencylayer.com) | USD (free), * (paid) | * | Yes |
| [1Forge](https://1forge.com) | * (free but limited or paid) | * (free but limited or paid) | No |
| [European Central Bank](https://www.ecb.europa.eu/home/html/index.en.html) | EUR | * | Yes |
| [National Bank of Romania](http://www.bnr.ro) | RON | * | Yes |
| [Central Bank of the Republic of Turkey](http://www.tcmb.gov.tr) | * | TRY | No |
| [Central Bank of the Czech Republic](https://www.cnb.cz) | * | CZK | Yes |
| [Central Bank of Russia](https://cbr.ru) | * | RUB | Yes |
| [WebserviceX](http://www.webservicex.net) | * | * | No |
| [Google](https://www.google.com/finance) | * | * | No |
| [Cryptonator](https://www.cryptonator.com) | * Crypto (Limited standard currencies) | * Crypto (Limited standard currencies)  | No |
| [CurrencyDataFeed](https://currencydatafeed.com) | * (free but limited or paid) | * (free but limited or paid) | No |
| [Open Exchange Rates](https://openexchangerates.org) | USD (free), * (paid) | * | Yes |
| [Xignite](https://www.xignite.com) | * | * | Yes |
| Array | * | * | Yes |

## Credits

- [Florian Voutzinos](https://github.com/florianv)
- [All Contributors](https://github.com/florianv/exchanger/contributors)

## License

The MIT License (MIT). Please see [LICENSE](https://github.com/florianv/exchanger/blob/master/LICENSE) for more information.
