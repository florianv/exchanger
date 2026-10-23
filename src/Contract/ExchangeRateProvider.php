<?php

declare(strict_types=1);

/*
 * This file is part of Exchanger.
 *
 * (c) Florian Voutzinos <florian@voutzinos.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Contract;

use Exchanger\Exception\Exception;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Contract for exchange rate providers.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
interface ExchangeRateProvider
{
    /**
     * Gets the exchange rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate
     *
     * @throws Exception                 When no rate can be provided. The subclass tells why:
     *                                   UnsupportedCurrencyPairException, UnsupportedDateException,
     *                                   UnsupportedExchangeQueryException, ChainException (Chain)
     *                                   or CacheException (Exchanger).
     * @throws ClientExceptionInterface  When the HTTP request to the provider fails.
     * @throws \RuntimeException         When the provider's response cannot be parsed, for services that don't wrap
     *                                   parse errors in Exception yet.
     * @throws InvalidArgumentException  When the PSR-16 cache rejects the cache key (Exchanger with a cache).
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate;
}
