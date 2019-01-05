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
use Exchanger\Service\NationalBankOfRomania;

class NationalBankOfRomaniaTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new NationalBankOfRomania($this->createMock('Http\Client\HttpClient'));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/RON'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     */
    public function it_throws_an_exception_when_quote_is_not_ron()
    {
        $url = 'http://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('RON/EUR')));
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
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/RON');
        $url = 'http://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(4.5125, $rate->getValue());
        $this->assertEquals(new \DateTime('2016-12-02'), $rate->getDate());
        $this->assertEquals('national_bank_of_romania', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_multiplier_rate()
    {
        $pair = CurrencyPair::createFromString('HUF/RON');
        $url = 'http://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.014356, $rate->getValue());
        $this->assertEquals(new \DateTime('2016-12-02'), $rate->getDate());
        $this->assertEquals('national_bank_of_romania', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/RON');
        $url = 'http://www.bnr.ro/files/xml/years/nbrfxrates2018.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates2018.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(
            new HistoricalExchangeRateQuery($pair, new \DateTime('2018-02-02'))
        );

        $this->assertSame(4.6526, $rate->getValue());
        $this->assertEquals(new \DateTime('2018-02-02'), $rate->getDate());
        $this->assertEquals('national_bank_of_romania', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedDateException
     * @expectedExceptionMessage The date "2018-02-25" is not supported by the service "Exchanger\Service\NationalBankOfRomania".
     */
    public function it_throws_an_exception_when_historical_date_is_not_supported()
    {
        $url = 'http://www.bnr.ro/files/xml/years/nbrfxrates2018.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates2018.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/RON'), new \DateTime('2018-02-25'))
        );
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     * @expectedExceptionMessage The currency pair "RON/XXL" is not supported by the service "Exchanger\Service\NationalBankOfRomania".
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported_historical()
    {
        $url = 'http://www.bnr.ro/files/xml/years/nbrfxrates2018.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates2018.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('RON/XXL'), new \DateTime('2018-02-02'))
        );
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new NationalBankOfRomania($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('national_bank_of_romania', $service->getName());
    }
}
