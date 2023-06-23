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

namespace Exchanger\Tests;

use Exchanger\ExchangeRate;
use Exchanger\ExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Exception\CacheException;
use Exchanger\Exception\UnsupportedExchangeQueryException;
use Exchanger\Exchanger;
use Exchanger\HistoricalExchangeRateQuery;
use PHPUnit\Framework\TestCase;

class ExchangerTest extends TestCase
{
    /**
     * @test
     */
    public function it_throws_an_exception_when_service_does_not_support_query()
    {
        $this->expectException(UnsupportedExchangeQueryException::class);
        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->willReturn(false);

        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));

        $exchanger = new Exchanger($service);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }

    /**
     * @test
     */
    public function it_quotes_a_pair()
    {
        $pair = CurrencyPair::createFromString('EUR/USD');
        $exchangeRateQuery = new ExchangeRateQuery($pair);
        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');
        $rate = new ExchangeRate($pair, 1, new \DateTime(), __CLASS__);

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->willReturn(true);

        $service
            ->expects($this->once())
            ->method('getExchangeRate')
            ->willReturn($rate);

        $exchanger = new Exchanger($service);

        $this->assertSame($rate, $exchanger->getExchangeRate($exchangeRateQuery));
    }

    /**
     * @test
     */
    public function it_quotes_an_identical_pair()
    {
        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/EUR'));

        $exchanger = new Exchanger($service);
        $rate = $exchanger->getExchangeRate($exchangeRateQuery);

        $this->assertSame(1.0, $rate->getValue());
        $this->assertInstanceOf('\DateTime', $rate->getDate());
        $this->assertEquals('null', $rate->getProviderName());
    }

    /**
     * @test
     */
    public function it_does_not_cache_identical_pairs()
    {
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/EUR'));
        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');
        $cache = $this->createMock('Psr\SimpleCache\CacheInterface');

        $cache
            ->expects($this->never())
            ->method('get');

        $exchanger = new Exchanger($service, $cache);
        $rate1 = $exchanger->getExchangeRate($exchangeRateQuery);
        $rate2 = $exchanger->getExchangeRate($exchangeRateQuery);

        $this->assertNotSame($rate1, $rate2, 'Identical pairs are not cached');
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_from_cache()
    {
        $pair = CurrencyPair::createFromString('EUR/USD');
        $exchangeRateQuery = new ExchangeRateQuery($pair);
        $rate = new ExchangeRate($pair, 1, new \DateTime(), __CLASS__);

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->willReturn(true);

        $cache = $this->createMock('Psr\SimpleCache\CacheInterface');

        $cache
            ->expects($this->once())
            ->method('get')
            ->willReturn($rate);

        $exchanger = new Exchanger($service, $cache);
        $this->assertSame($rate, $exchanger->getExchangeRate($exchangeRateQuery));
    }

    /**
     * @test
     */
    public function it_caches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/USD');
        $exchangeRateQuery = new ExchangeRateQuery($pair);
        $rate = new ExchangeRate($pair, 1, new \DateTime(), __CLASS__);
        $ttl = 3600;
        $key = sha1("{$exchangeRateQuery->getCurrencyPair()->getBaseCurrency()}{$exchangeRateQuery->getCurrencyPair()->getQuoteCurrency()}");

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->willReturn(true);

        $service
            ->expects($this->once())
            ->method('getExchangeRate')
            ->willReturn($rate);

        $cache = $this->createMock('Psr\SimpleCache\CacheInterface');

        $cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $cache
            ->expects($this->once())
            ->method('set')
            ->with($key, $rate, $ttl);

        $exchanger = new Exchanger($service, $cache, ['cache_ttl' => $ttl]);

        $returnedRate = $exchanger->getExchangeRate($exchangeRateQuery);

        $this->assertSame($rate, $returnedRate);
    }

    /**
     * @test
     */
    public function it_caches_a_rate_historical_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/USD');
        $exchangeRateQuery = new HistoricalExchangeRateQuery($pair, new \DateTime('2020-12-01', new \DateTimeZone('UTC')));
        $rate = new ExchangeRate($pair, 1, new \DateTime('2020-12-01', new \DateTimeZone('UTC')), __CLASS__);
        $ttl = 3600;
        $key = sha1("{$exchangeRateQuery->getCurrencyPair()->getBaseCurrency()}{$exchangeRateQuery->getCurrencyPair()->getQuoteCurrency()}2020-12-01");

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->willReturn(true);

        $service
            ->expects($this->once())
            ->method('getExchangeRate')
            ->willReturn($rate);

        $cache = $this->createMock('Psr\SimpleCache\CacheInterface');

        $cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $cache
            ->expects($this->once())
            ->method('set')
            ->with($key, $rate, $ttl);

        $exchanger = new Exchanger($service, $cache, ['cache_ttl' => $ttl]);

        $returnedRate = $exchanger->getExchangeRate($exchangeRateQuery);

        $this->assertSame($rate, $returnedRate);
    }

    /**
     * @test
     */
    public function it_does_not_use_cache_if_cache_false()
    {
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), ['cache' => false]);

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->once())
            ->method('supportQuery')
            ->willReturn(true);

        $service
            ->expects($this->once())
            ->method('getExchangeRate');

        $cache = $this->createMock('Psr\SimpleCache\CacheInterface');

        $cache
            ->expects($this->never())
            ->method('get');

        $cache
            ->expects($this->never())
            ->method('set');

        $exchanger = new Exchanger($service, $cache);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }

    /**
     * @test
     */
    public function it_supports_overrding_ttl_per_query()
    {
        $ttl = 3600;
        $pair = CurrencyPair::createFromString('EUR/USD');
        $exchangeRateQuery = new ExchangeRateQuery($pair, ['cache_ttl' => $ttl]);
        $rate = new ExchangeRate($pair, 1, new \DateTime(), __CLASS__);
        $key = sha1("{$exchangeRateQuery->getCurrencyPair()->getBaseCurrency()}{$exchangeRateQuery->getCurrencyPair()->getQuoteCurrency()}");

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->willReturn(true);

        $service
            ->expects($this->once())
            ->method('getExchangeRate')
            ->willReturn($rate);

        $cache = $this->createMock('Psr\SimpleCache\CacheInterface');

        $cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $cache
            ->expects($this->once())
            ->method('set')
            ->with($key, $rate, $ttl);

        $exchanger = new Exchanger($service, $cache, ['cache_ttl' => 60]);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }

    /**
     * @test
     */
    public function it_supports_overrding_cache_prefix_per_query()
    {
        $expectedKeyPrefix = 'expected-prefix';
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), ['cache_key_prefix' => $expectedKeyPrefix]);

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');
        $cache = $this->createMock('Psr\SimpleCache\CacheInterface');

        $service
            ->expects($this->once())
            ->method('supportQuery')
            ->willReturn(true);

        $cache
            ->expects($this->once())
            ->method('get')
            ->with($this->stringStartsWith($expectedKeyPrefix));

        $exchanger = new Exchanger($service, $cache);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_if_cache_key_is_too_long()
    {
        $this->expectException(CacheException::class);
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $cache = $this->createMock('Psr\SimpleCache\CacheInterface');

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->willReturn(true);

        $exchanger = new Exchanger($service, $cache, ['cache_key_prefix' => 'prefix_longer_then_24_symbols']);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_if_service_cant_support_pair()
    {
        $this->expectException(UnsupportedExchangeQueryException::class);
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->once())
            ->method('supportQuery')
            ->willReturn(false);

        $exchanger = new Exchanger($service);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }
}
