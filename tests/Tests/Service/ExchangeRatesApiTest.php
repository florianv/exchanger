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

use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\ExchangeRatesApi;

/**
 * @author Arjan Westdorp <arjanwestdorp@gmail.com>
 */
class ExchangeRatesApiTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_support_all_queries()
    {
        $service = new ExchangeRatesApi($this->createMock('Http\Client\HttpClient'));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
    }

    /**
     * @test
     */
    public function it_supports_eur_base()
    {
        $service = new ExchangeRatesApi($this->createMock('Http\Client\HttpClient'));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/CAD'))));
    }

    /**
     * @test
     */
    public function it_does_support_other_than_eur()
    {
        $service = new ExchangeRatesApi($this->createMock('Http\Client\HttpClient'));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/CAD'))));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     * @expectedExceptionMessage Base 'FOO' is not supported.
     */
    public function it_throws_an_exception_with_error_response()
    {
        $uri = 'https://api.exchangeratesapi.io/latest?base=FOO';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/ExchangeRatesApi/error.json');

        $service = new ExchangeRatesApi($this->getHttpAdapterMock($uri, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('FOO/EUR')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/CHF');
        $uri = 'https://api.exchangeratesapi.io/latest?base=EUR';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/ExchangeRatesApi/latest.json');

        $service = new ExchangeRatesApi($this->getHttpAdapterMock($uri, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertEquals(1.0933, $rate->getValue());
        $this->assertEquals(new \DateTime('2016-08-26'), $rate->getDate());
        $this->assertEquals('exchange_rates_api', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }
    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/AUD');
        $uri = 'https://api.exchangeratesapi.io/2000-01-03?base=EUR';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/ExchangeRatesApi/historical.json');
        $date = new \DateTime('2000-01-03');

        $service = new ExchangeRatesApi($this->getHttpAdapterMock($uri, $content));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEquals(1.5209, $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('exchange_rates_api', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new ExchangeRatesApi($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('exchange_rates_api', $service->getName());
    }
}
