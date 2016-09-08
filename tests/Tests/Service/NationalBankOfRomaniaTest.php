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
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     */
    public function it_throws_an_exception_when_base_is_not_ron()
    {
        $url = 'http://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/RON')));
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
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('RON/XXX')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $url = 'http://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('RON/EUR')));

        $this->assertSame('4.4856', $rate->getValue());
        $this->assertEquals(new \DateTime('2015-01-12'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_multiplier_rate()
    {
        $url = 'http://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('RON/HUF')));

        $this->assertSame('0.014092', $rate->getValue());
        $this->assertEquals(new \DateTime('2015-01-12'), $rate->getDate());
    }
}
