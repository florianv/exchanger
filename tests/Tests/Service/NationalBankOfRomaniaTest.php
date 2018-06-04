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
use Exchanger\Service\NationalBankOfRomania;

class NationalBankOfRomaniaTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new NationalBankOfRomania($this->getMock('Http\Client\HttpClient'));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/RON'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('RON/EUR'))));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('RON/EUR'), new \DateTime())));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $url = 'http://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/RON')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_with_ron_as_quote()
    {
        $url = 'http://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/RON')));

        $this->assertSame('4.5125', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-12-02'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_with_ron_as_base()
    {
        $url = 'http://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('RON/EUR')));

        $this->assertSame('0.2216', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-12-02'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_multiplier_rate()
    {
        $url = 'http://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('HUF/RON')));

        $this->assertSame('0.014356', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-12-02'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate_with_ron_as_quote()
    {
        $url = 'http://www.bnr.ro/files/xml/years/nbrfxrates2018.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates2018.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/RON'), new \DateTime('2018-02-02'))
        );

        $this->assertSame('4.6526', $rate->getValue());
        $this->assertEquals(new \DateTime('2018-02-02'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate_with_ron_as_base()
    {
        $url = 'http://www.bnr.ro/files/xml/years/nbrfxrates2018.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates2018.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('RON/EUR'), new \DateTime('2018-02-02'))
        );

        $this->assertSame('0.2149', $rate->getValue());
        $this->assertEquals(new \DateTime('2018-02-02'), $rate->getDate());
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedDateException
     */
    public function it_throws_an_exception_when_historical_date_is_not_supported()
    {
        $url = 'http://www.bnr.ro/files/xml/years/nbrfxrates2018.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates2018.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/RON'), new \DateTime('tomorrow'))
        );
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     * @expectedExceptionMessage The currency pair "RON/XXX" is not supported by the service "Exchanger\Service\NationalBankOfRomania".
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported_historical()
    {
        $url = 'http://www.bnr.ro/files/xml/years/nbrfxrates2018.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates2018.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('RON/XXX'), new \DateTime('2018-02-02'))
        );
    }
}
