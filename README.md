# Exchanger

> Currency exchange rates framework for PHP

[![Build status](http://img.shields.io/travis/florianv/exchanger/master.svg?style=flat-square)](https://travis-ci.org/florianv/exchanger)
[![Total Downloads](https://img.shields.io/packagist/dt/florianv/exchanger.svg?style=flat-square)](https://packagist.org/packages/florianv/exchanger)
[![Scrutinizer](https://img.shields.io/scrutinizer/g/florianv/exchanger.svg?style=flat-square)](https://scrutinizer-ci.com/g/florianv/exchanger)
[![Version](http://img.shields.io/packagist/v/florianv/exchanger.svg?style=flat-square)](https://packagist.org/packages/florianv/exchanger)

`Exchanger` is a PHP framework to work with currency exchange rates.

**Looking for a simple library based on `Exchanger` ? Check out [Swap](https://github.com/florianv/swap) !**

## Documentation

The documentation can be found [here](https://github.com/florianv/exchanger/blob/master/doc/readme.md).

## Services

Here is the list of the currently implemented services.

| Service | Base Currency | Quote Currency | Historical |
|---------------------------------------------------------------------------|----------------------|----------------|----------------|
| [Fixer](http://fixer.io) | EUR (free, no SSL), * (paid) | * | Yes |
| [European Central Bank](http://www.ecb.europa.eu/home/html/index.en.html) | EUR | * | Yes |
| [Google](http://www.google.com/finance) | * | * | No |
| [Open Exchange Rates](https://openexchangerates.org) | USD (free), * (paid) | * | Yes |
| [Xignite](https://www.xignite.com) | * | * | Yes |
| [WebserviceX](http://www.webservicex.net/ws/default.aspx) | * | * | No |
| [National Bank of Romania](http://www.bnr.ro) | RON | * | Yes |
| [Central Bank of the Republic of Turkey](http://www.tcmb.gov.tr) | * | TRY | No |
| [Central Bank of the Czech Republic](http://www.cnb.cz) | * | CZK | Yes |
| [Russian Central Bank](http://http://www.cbr.ru) | * | RUB | Yes |
| [currencylayer](https://currencylayer.com) | USD (free), * (paid) | * | Yes |
| [Cryptonator](https://www.cryptonator.com) | * Crypto (Limited standard currencies) | * Crypto (Limited standard currencies)  | No |
| [1Forge](https://1forge.com) | * (free but limited or paid) | * (free but limited or paid) | No |
| [CurrencyDataFeed](https://currencydatafeed.com) | * (free but limited or paid) | * (free but limited or paid) | No |
| Array | * | * | Yes |

## Credits

- [Florian Voutzinos](https://github.com/florianv)
- [All Contributors](https://github.com/florianv/exchanger/contributors)

## License

The MIT License (MIT). Please see [LICENSE](https://github.com/florianv/exchanger/blob/master/LICENSE) for more information.
