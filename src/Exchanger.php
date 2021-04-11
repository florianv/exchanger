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

namespace Exchanger;

use Exchanger\Contract\ExchangeRateProvider as ExchangeRateProviderContract;
use Exchanger\Contract\ExchangeRateQuery as ExchangeRateQueryContract;
use Exchanger\Contract\ExchangeRateService as ExchangeRateServiceContract;
use Exchanger\Exception\CacheException;
use Exchanger\Exception\UnsupportedExchangeQueryException;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;
use Psr\SimpleCache\CacheInterface;

/**
 * Default implementation of the exchange rate provider with PSR-6 caching support.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class Exchanger implements ExchangeRateProviderContract
{
    /**
     * The service.
     *
     * @var ExchangeRateServiceContract
     */
    private $service;

    /**
     * The cache item pool.
     *
     * @var CacheInterface
     */
    private $cache;

    /**
     * The options.
     *
     * @var array
     */
    private $options;

    /**
     * Constructor.
     *
     * @param ExchangeRateServiceContract $service
     * @param CacheInterface|null         $cache
     * @param array                       $options
     */
    public function __construct(ExchangeRateServiceContract $service, CacheInterface $cache = null, array $options = [])
    {
        $this->service = $service;
        $this->cache = $cache;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQueryContract $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if ($currencyPair->isIdentical()) {
            return new ExchangeRate($currencyPair, 1, new \DateTime(), 'null');
        }

        if (!$this->service->supportQuery($exchangeQuery)) {
            throw new UnsupportedExchangeQueryException($exchangeQuery, $this->service);
        }

        if (null === $this->cache || false === $exchangeQuery->getOption('cache')) {
            return $this->service->getExchangeRate($exchangeQuery);
        }

        $cacheKeyPrefix = isset($this->options['cache_key_prefix']) ? $this->options['cache_key_prefix'] : '';
        $cacheKeyPrefix = $exchangeQuery->getOption('cache_key_prefix', $cacheKeyPrefix);

        // Replace characters reserved in PSR-6
        $cacheKeyPrefix = preg_replace('#[\{\}\(\)/\\\@\:]#', '-', $cacheKeyPrefix);

        $cacheKey = $cacheKeyPrefix.sha1(serialize($exchangeQuery));
        if (\strlen($cacheKey) > 64) {
            throw new CacheException("Cache key length exceeds 64 characters ('$cacheKey'). This violates PSR-6 standard");
        }

        $item = $this->cache->get($cacheKey);

        if (null !== $item && $item instanceof ExchangeRateContract) {
            return $item;
        }

        $rate = $this->service->getExchangeRate($exchangeQuery);
        $ttl = $exchangeQuery->getOption('cache_ttl', isset($this->options['cache_ttl']) ? $this->options['cache_ttl'] : null);

        $this->cache->set($cacheKey, $rate, $ttl);

        return $rate;
    }
}
