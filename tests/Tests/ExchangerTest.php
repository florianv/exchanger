<?php

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
use Exchanger\Exchanger;
use PHPUnit\Framework\TestCase;

class ExchangerTest extends TestCase
{
    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedExchangeQueryException
     */
    public function it_throws_an_exception_when_service_does_not_support_query()
    {
        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->will($this->returnValue(false));

        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));

        $exchanger = new Exchanger($service);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }

    /**
     * @test
     */
    public function it_quotes_a_pair()
    {
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));
        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');
        $rate = new ExchangeRate('1', new \DateTime());

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $service
            ->expects($this->once())
            ->method('getExchangeRate')
            ->will($this->returnValue($rate));

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

        $this->assertSame('1', $rate->getValue());
        $this->assertInstanceOf('\DateTime', $rate->getDate());
    }

    /**
     * @test
     */
    public function it_does_not_cache_identical_pairs()
    {
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/EUR'));
        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');
        $pool = $this->createMock('Psr\Cache\CacheItemPoolInterface');

        $pool
            ->expects($this->never())
            ->method('getItem');

        $exchanger = new Exchanger($service, $pool);
        $rate1 = $exchanger->getExchangeRate($exchangeRateQuery);
        $rate2 = $exchanger->getExchangeRate($exchangeRateQuery);

        $this->assertNotSame($rate1, $rate2, 'Identical pairs are not cached');
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_from_cache()
    {
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));
        $rate = new ExchangeRate('1', new \DateTime());

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $item = $this->createMock('Psr\Cache\CacheItemInterface');

        $item
            ->expects($this->once())
            ->method('isHit')
            ->will($this->returnValue(true));

        $item
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue($rate));

        $pool = $this->createMock('Psr\Cache\CacheItemPoolInterface');

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with(sha1(serialize($exchangeRateQuery)))
            ->will($this->returnValue($item));

        $exchanger = new Exchanger($service, $pool);
        $this->assertSame($rate, $exchanger->getExchangeRate($exchangeRateQuery));
    }

    /**
     * @test
     */
    public function it_caches_a_rate()
    {
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));
        $rate = new ExchangeRate('1', new \DateTime());
        $ttl = 3600;

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $service
            ->expects($this->once())
            ->method('getExchangeRate')
            ->will($this->returnValue($rate));

        $item = $this->createMock('Psr\Cache\CacheItemInterface');

        $item
            ->expects($this->once())
            ->method('isHit')
            ->will($this->returnValue(false));

        $item
            ->expects($this->once())
            ->method('set')
            ->with($rate);

        $item
            ->expects($this->once())
            ->method('expiresAfter')
            ->with($ttl);

        $pool = $this->createMock('Psr\Cache\CacheItemPoolInterface');

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with(sha1(serialize($exchangeRateQuery)))
            ->will($this->returnValue($item));

        $pool
            ->expects($this->once())
            ->method('save')
            ->with($item);

        $exchanger = new Exchanger($service, $pool, ['cache_ttl' => $ttl]);

        $exchanger->getExchangeRate($exchangeRateQuery);
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
            ->will($this->returnValue(true));

        $service
            ->expects($this->once())
            ->method('getExchangeRate');

        $item = $this->createMock('Psr\Cache\CacheItemInterface');

        $item
            ->expects($this->never())
            ->method('get');

        $pool = $this->createMock('Psr\Cache\CacheItemPoolInterface');

        $pool
            ->expects($this->never())
            ->method('getItem')
            ->will($this->returnValue($item));

        $pool
            ->expects($this->never())
            ->method('save')
            ->with($item);

        $exchanger = new Exchanger($service, $pool);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }

    /**
     * @test
     */
    public function it_supports_overrding_ttl_per_query()
    {
        $ttl = 3600;
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), ['cache_ttl' => $ttl]);
        $rate = new ExchangeRate('1', new \DateTime());

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $service
            ->expects($this->once())
            ->method('getExchangeRate')
            ->will($this->returnValue($rate));

        $item = $this->createMock('Psr\Cache\CacheItemInterface');

        $item
            ->expects($this->once())
            ->method('isHit')
            ->will($this->returnValue(false));

        $item
            ->expects($this->once())
            ->method('set')
            ->with($rate);

        $item
            ->expects($this->once())
            ->method('expiresAfter')
            ->with($ttl);

        $pool = $this->createMock('Psr\Cache\CacheItemPoolInterface');

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->will($this->returnValue($item));

        $pool
            ->expects($this->once())
            ->method('save')
            ->with($item);

        $exchanger = new Exchanger($service, $pool, ['cache_ttl' => 60]);
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
        $item = $this->createMock('Psr\Cache\CacheItemInterface');
        $pool = $this->createMock('Psr\Cache\CacheItemPoolInterface');

        $service
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with($this->stringStartsWith($expectedKeyPrefix))
            ->will($this->returnValue($item));

        $exchanger = new Exchanger($service, $pool);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\CacheException
     */
    public function it_throws_an_exception_if_cache_key_is_too_long()
    {
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $pool = $this->createMock('Psr\Cache\CacheItemPoolInterface');
        $service
            ->expects($this->any())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $exchanger = new Exchanger($service, $pool, ['cache_key_prefix' => 'prefix_longer_then_24_symbols']);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedExchangeQueryException
     */
    public function it_throws_an_exception_if_service_cant_support_pair()
    {
        $exchangeRateQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));

        $service = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $service
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(false));

        $exchanger = new Exchanger($service);
        $exchanger->getExchangeRate($exchangeRateQuery);
    }
}
