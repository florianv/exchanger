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

use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\Service\Google;

class GoogleTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new Google($this->getMock('Http\Client\HttpClient'));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     */
    public function it_throws_an_exception_when_rate_not_supported()
    {
        $uri = 'https://www.google.com/search?q=1+EUR+to+XXL';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/GoogleFinance/unsupported.html');

        $service = new Google($this->getGoogleHttpAdapterMock($uri, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/XXL')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $url = 'https://www.google.com/search?q=1+EUR+to+MXN';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/GoogleFinance/success.html');

        $service = new Google($this->getGoogleHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/MXN')));

        $this->assertSame('23.1021173', $rate->getValue());
        $this->assertInstanceOf('\DateTime', $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_formated_with_coma()
    {
        $url = 'https://www.google.com/search?q=1+EUR+to+MXN';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/GoogleFinance/success2.html');

        $service = new Google($this->getGoogleHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/MXN')));

        $this->assertSame('23.10', $rate->getValue());
        $this->assertInstanceOf('\DateTime', $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_colombian_rate()
    {
        $url = 'https://www.google.com/search?q=1+EUR+to+COP';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/GoogleFinance/success_colombian.html');

        $service = new Google($this->getGoogleHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/COP')));

        $this->assertSame('3424.88889', $rate->getValue());
        $this->assertInstanceOf('\DateTime', $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_colombian_rate_other()
    {
        $url = 'https://www.google.com/search?q=1+EUR+to+COP';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/GoogleFinance/success_colombian_other.html');

        $service = new Google($this->getGoogleHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/COP')));

        $this->assertSame('3529.33', $rate->getValue());
        $this->assertInstanceOf('\DateTime', $rate->getDate());
    }

    /**
     * @test
     */
    public function it_parses_the_bid()
    {
        $service = new Google();

        $this->assertSame('1.10', $service->parseNumber('1.10'));
        $this->assertSame('1.10', $service->parseNumber('1,10'));
        $this->assertSame('1100.12', $service->parseNumber('1,100.12'));
        $this->assertSame('1100.12', $service->parseNumber('1.100,12'));
        $this->assertSame('1100.12', $service->parseNumber('1100,12'));
        $this->assertSame('1100.12', $service->parseNumber('1100.12'));
        $this->assertSame('1100', $service->parseNumber('1100'));
    }
}
