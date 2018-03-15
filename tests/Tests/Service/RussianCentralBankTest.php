<?php

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
use Exchanger\Service\RussianCentralBank;

class RussianCentralBankTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new RussianCentralBank($this->getMock('Http\Client\HttpClient'));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/RUB'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/RUB'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     * @expectedExceptionMessage The currency pair "XXL/RUB" is not supported by the service "Exchanger\Service\RussianCentralBank".
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $url = 'http://www.cbr.ru/scripts/XML_daily.asp';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/RussianCentralBank/success.xml');

        $service = new RussianCentralBank($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXL/RUB')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $url = 'http://www.cbr.ru/scripts/XML_daily.asp';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/RussianCentralBank/success.xml');

        $service = new RussianCentralBank($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/RUB')));

        $this->assertSame('68.2458', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-12-09'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_nominational_rate()
    {
        $url = 'http://www.cbr.ru/scripts/XML_daily.asp';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/RussianCentralBank/success.xml');

        $service = new RussianCentralBank($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('AMD/RUB')));

        $this->assertSame('0.131783', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-12-09'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $url = 'http://www.cbr.ru/scripts/XML_daily.asp?date_req=23.08.2016';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/RussianCentralBank/historical.xml');

        $service = new RussianCentralBank($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('USD/RUB'), new \DateTime('2016-08-23'))
        );

        $this->assertSame('64.2078', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-08-23'), $rate->getDate());
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedDateException
     * @expectedExceptionMessage The date "1986-08-23" is not supported by the service "Exchanger\Service\RussianCentralBank".
     */
    public function it_throws_an_exception_when_historical_date_is_not_supported()
    {
        $url = 'http://www.cbr.ru/scripts/XML_daily.asp?date_req=23.08.1986';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/RussianCentralBank/historical_error.xml');

        $service = new RussianCentralBank($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/RUB'), new \DateTime('1986-08-23')));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     * @expectedExceptionMessage The currency pair "XXL/RUB" is not supported by the service "Exchanger\Service\RussianCentralBank".
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported_historical()
    {
        $url = 'http://www.cbr.ru/scripts/XML_daily.asp?date_req=23.08.2016';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/RussianCentralBank/historical.xml');

        $service = new RussianCentralBank($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('XXL/RUB'), new \DateTime('2016-08-23')));
    }
}
