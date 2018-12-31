# Release Notes

## 2.0.0

- Migrated from PHP 5.5 to PHP 7.1+

- Updated PHP HTTP dependency to version 2

- Removed the deprecated Yahoo service which has been removed by Yahoo

- Removed the deprecated Google service which has been very unreliable and unstable on different platforms

- Fixed the exchange rate value to always be a float instead of a string

- Added information about which service returned a rate with `ExchangeRate::getProvider()`

- Removed the `InternalException` only used in the `PhpArray` service

- Modified the PHPArray service to only support scalars as rates (rate objects are not compatible)

- We now rely on PSR-16 Simple Cache instead of PSR-6 Cache
