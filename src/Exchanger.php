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

use Exchanger\Contract\ExchangeRateProvider as ExchangeRateProviderContract;
use Exchanger\Contract\ExchangeRateQuery as ExchangeRateQueryContract;
use Exchanger\Contract\ExchangeRateService as ExchangeRateServiceContract;
use Exchanger\Exception\CacheException;
use Exchanger\Exception\UnsupportedExchangeQueryException;
use Psr\Cache\CacheItemPoolInterface;

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

        $cacheKeyPrefix = isset($this->options['cache_key_prefix']) ? $this->options['cache_key_prefix'] : '';
        $cacheKeyPrefix = $exchangeQuery->getOption('cache_key_prefix', $cacheKeyPrefix);

        // Replace characters reserved in PSR-6
        $cacheKeyPrefix = preg_replace('#[\{\}\(\)/\\\@\:]#', '-', $cacheKeyPrefix);

        $cacheKey = $cacheKeyPrefix.sha1(serialize($exchangeQuery));
        if (strlen($cacheKey) > 64) {
            throw new CacheException("Cache key length exceeds 64 characters ('$cacheKey'). This violates PSR-6 standard");
        }

        $item = $this->cacheItemPool->getItem($cacheKey);

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
