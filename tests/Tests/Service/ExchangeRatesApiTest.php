<?php
/*
 * This file is part of Exchanger.
 *
 * (c) Jonas Hansen <jonas.kerwin.hansen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Tests\Service;

use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\ExchangeRatesApi;

class ExchangeRatesApiTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_supports_eur_base()
    {
        $service = new ExchangeRatesApi($this->getMock('Http\Client\HttpClient'));
        $this->assertTrue(
            $service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/DKK')))
        );
    }

    /**
     * @test
     */
    public function it_supports_other_than_eur_base()
    {
        $service = new ExchangeRatesApi($this->getMock('Http\Client\HttpClient'));
        $this->assertTrue(
            $service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('DKK/SEK')))
        );
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     * @expectedExceptionMessage Base 'CSV' is not supported.
     */
    public function it_throws_an_exception_with_error_response()
    {
        $uri = 'https://exchangeratesapi.io/api/latest?base=CSV';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/ExchangeRatesApi/error.json');
        
        $service = new ExchangeRatesApi($this->getHttpAdapterMock($uri, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('CSV/EUR')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $uri = 'https://exchangeratesapi.io/api/latest?base=EUR';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/ExchangeRatesApi/latest.json');

        $service = new ExchangeRatesApi($this->getHttpAdapterMock($uri, $content));
        $rate = $service->getExchangeRate(
            new ExchangeRateQuery(CurrencyPair::createFromString('EUR/DKK'))
        );

        $this->assertEquals('7.4555', $rate->getValue());
        $this->assertEquals(new \DateTime('2018-07-13'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $uri = 'https://exchangeratesapi.io/api/2000-01-03?base=EUR';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/ExchangeRatesApi/historical.json');
        $date = new \DateTime('2000-01-03');

        $service = new ExchangeRatesApi($this->getHttpAdapterMock($uri, $content));
        $rate = $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/DKK'), $date)
        );

        $this->assertEquals('7.4404', $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
    }
}
