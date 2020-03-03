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

namespace Exchanger\Tests\Service;

use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\CoinLayer;

/**
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class CoinLayerTest extends ServiceTestCase
{
    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The "access_key" option must be provided.
     */
    public function it_throws_an_exception_if_access_key_option_missing()
    {
        new CoinLayer($this->createMock('Http\Client\HttpClient'));
    }

    /**
     * @test
     */
    public function it_supports_all_queries()
    {
        $service = new CoinLayer($this->createMock('Http\Client\HttpClient'), null, ['access_key' => 'secret']);
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/EUR'))));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     */
    public function it_throws_an_exception_with_error_response()
    {
        $uri = 'http://api.coinlayer.com/api/live?access_key=secret&symbols=BTC&target=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CoinLayer/error.json');

        $service = new CoinLayer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'secret']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('BTC/USD')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_normal_mode()
    {
        $uri = 'http://api.coinlayer.com/api/live?access_key=secret&symbols=BTC&target=USD';
        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp(1583227144);
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CoinLayer/success.json');

        $pair = CurrencyPair::createFromString('BTC/USD');
        $service = new CoinLayer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'secret']);
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertEquals(8822.633507, $rate->getValue());
        $this->assertEquals($expectedDate, $rate->getDate());
        $this->assertEquals('coin_layer', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_paid_mode()
    {
        $uri = 'https://api.coinlayer.com/api/live?access_key=secret&symbols=BTC&target=USD';
        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp(1583227144);
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CoinLayer/success.json');

        $pair = CurrencyPair::createFromString('BTC/USD');
        $service = new CoinLayer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'secret', 'paid' => true]);
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertEquals(8822.633507, $rate->getValue());
        $this->assertEquals($expectedDate, $rate->getDate());
        $this->assertEquals('coin_layer', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate_normal_mode()
    {
        $pair = CurrencyPair::createFromString('ETH/USD');
        $uri = 'http://api.coinlayer.com/api/2015-05-06?access_key=secret&symbols=ETH&target=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CoinLayer/historical_success.json');
        $date = new \DateTime('2015-05-06');
        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp(1577923149);

        $service = new CoinLayer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'secret']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEquals(131.312114, $rate->getValue());
        $this->assertEquals($expectedDate, $rate->getDate());
        $this->assertEquals('coin_layer', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate_paid_mode()
    {
        $pair = CurrencyPair::createFromString('ETH/USD');
        $uri = 'https://api.coinlayer.com/api/2015-05-06?access_key=secret&symbols=ETH&target=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CoinLayer/historical_success.json');
        $date = new \DateTime('2015-05-06');
        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp(1577923149);

        $service = new CoinLayer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'secret', 'paid' => true]);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEquals(131.312114, $rate->getValue());
        $this->assertEquals($expectedDate, $rate->getDate());
        $this->assertEquals('coin_layer', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new CoinLayer($this->createMock('Http\Client\HttpClient'), null, ['access_key' => 'secret', 'enterprise' => true]);

        $this->assertSame('coin_layer', $service->getName());
    }
}
