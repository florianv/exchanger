<?php
/*
 * This file is part of Exchanger.
 *
 * (c) Pascal Hofmann <mail@pascalhofmann.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Tests\Service;

use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\Fixer;

class FixerTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new Fixer($this->getMock('Http\Client\HttpClient'), null, ['access_key' => 'x']);
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
    }

    /**
     * @test
     */
    public function it_supports_eur_base_normal_mode()
    {
        $service = new Fixer($this->getMock('Http\Client\HttpClient'), null, ['access_key' => 'x']);
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/CAD'))));
    }

    /**
     * @test
     */
    public function it_does_not_support_other_than_eur_base_in_normal_mode()
    {
        $service = new Fixer($this->getMock('Http\Client\HttpClient'), null, ['access_key' => 'x']);
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/CAD'))));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     * @expectedExceptionMessage The current subscription plan does not support this API endpoint.
     */
    public function it_throws_an_exception_with_error_response()
    {
        $uri = 'http://data.fixer.io/api/latest?access_key=x';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Fixer/error.json');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'x']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_normal_mode()
    {
        $uri = 'http://data.fixer.io/api/latest?access_key=x';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Fixer/latest.json');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'x']);
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/CHF')));

        $this->assertEquals('1.0933', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-08-26'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_enterprise_mode()
    {
        $uri = 'http://data.fixer.io/api/latest?base=EUR&access_key=x';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Fixer/latest.json');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'x', 'enterprise' => true ]);
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/CHF')));

        $this->assertEquals('1.0933', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-08-26'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate_normal_mode()
    {
        $uri = 'http://data.fixer.io/api/2000-01-03?access_key=x';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Fixer/historical.json');
        $date = new \DateTime('2000-01-03');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'x']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/AUD'), $date));

        $this->assertEquals('1.5209', $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate_enterprise_mode()
    {
        $uri = 'http://data.fixer.io/api/2000-01-03?base=EUR&access_key=x';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Fixer/historical.json');
        $date = new \DateTime('2000-01-03');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'x', 'enterprise' => true]);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/AUD'), $date));

        $this->assertEquals('1.5209', $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
    }
}
