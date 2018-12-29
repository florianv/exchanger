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
use Exchanger\Service\EuropeanCentralBank;

class EuropeanCentralBankTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new EuropeanCentralBank($this->createMock('Http\Client\HttpClient'));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     * @expectedExceptionMessage The currency pair "EUR/XXL" is not supported by the service "Exchanger\Service\EuropeanCentralBank".
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/EuropeanCentralBank/success.xml');

        $service = new EuropeanCentralBank($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/XXL')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/EuropeanCentralBank/success.xml');

        $service = new EuropeanCentralBank($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/BGN')));

        $this->assertSame(1.9558, $rate->getValue());
        $this->assertEquals(new \DateTime('2015-01-07'), $rate->getDate());
        $this->assertEquals(EuropeanCentralBank::class, $rate->getProvider());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist-90d.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/EuropeanCentralBank/historical.xml');

        $service = new EuropeanCentralBank($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/JPY'), new \DateTime('2016-08-23'))
        );

        $this->assertSame(113.48, $rate->getValue());
        $this->assertEquals(new \DateTime('2016-08-23'), $rate->getDate());
        $this->assertEquals(EuropeanCentralBank::class, $rate->getProvider());
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedDateException
     * @expectedExceptionMessage The date "2015-08-23" is not supported by the service "Exchanger\Service\EuropeanCentralBank".
     */
    public function it_throws_an_exception_when_historical_date_is_not_supported()
    {
        $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist-90d.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/EuropeanCentralBank/historical.xml');

        $service = new EuropeanCentralBank($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/JPY'), new \DateTime('2015-08-23')));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     * @expectedExceptionMessage The currency pair "EUR/XXL" is not supported by the service "Exchanger\Service\EuropeanCentralBank".
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported_historical()
    {
        $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist-90d.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/EuropeanCentralBank/historical.xml');

        $service = new EuropeanCentralBank($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/XXL'), new \DateTime('2016-08-23')));
    }
}
