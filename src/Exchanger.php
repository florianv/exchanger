<?php

/*
 * This file is part of Exchanger.
 *
 * (c) Florian Voutzinos <florian@voutzinos.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger;

use Psr\Cache\CacheItemPoolInterface;
use Exchanger\Contract\ExchangeRateProvider as ExchangeRateProviderContract;
use Exchanger\Contract\ExchangeRateQuery as ExchangeRateQueryContract;
use Exchanger\Contract\ExchangeRateService as ExchangeRateServiceContract;
use Exchanger\Exception\UnsupportedExchangeQueryException;

/**
 * Default implementation of the exchange rate provider with PSR-6 caching support.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class Exchanger implements ExchangeRateProviderContract
{
    private $service;
    private $cacheItemPool;
    private $options;

    public function __construct(ExchangeRateServiceContract $service, CacheItemPoolInterface $cacheItemPool = null, array $options = [])
    {
        $this->service = $service;
        $this->cacheItemPool = $cacheItemPool;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQueryContract $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if ($currencyPair->isIdentical()) {
            return new ExchangeRate(1);
        }

        if (!$this->service->supportQuery($exchangeQuery)) {
            throw new UnsupportedExchangeQueryException($exchangeQuery, $this->service);
        }

        if (null === $this->cacheItemPool || false === $exchangeQuery->getOption('cache')) {
            return $this->service->getExchangeRate($exchangeQuery);
        }

        $item = $this->cacheItemPool->getItem(sha1(serialize($exchangeQuery)));

        if ($item->isHit()) {
            return $item->get();
        }

        $rate = $this->service->getExchangeRate($exchangeQuery);

        $item->set($rate);
        $item->expiresAfter($exchangeQuery->getOption('cache_ttl', isset($this->options['cache_ttl']) ? $this->options['cache_ttl'] : null));

        $this->cacheItemPool->save($item);

        return $rate;
    }
}
