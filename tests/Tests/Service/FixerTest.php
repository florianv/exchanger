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
    public function it_supports_all_queries()
    {
        $service = new Fixer($this->getMock('Http\Client\HttpClient'), null, ['access_key' => 'x']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     */
    public function it_throws_an_exception_with_error_response()
    {
        $uri = 'https://api.fixer.io/latest?base=USD&access_key=x';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Fixer/error.json');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'x']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $uri = 'https://api.fixer.io/latest?base=EUR&access_key=x';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Fixer/latest.json');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'x']);
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/CHF')));

        $this->assertEquals('1.0933', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-08-26'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $uri = 'https://api.fixer.io/2000-01-03?base=USD&access_key=x';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Fixer/historical.json');
        $date = new \DateTime('2000-01-03');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'x']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('USD/AUD'), $date));

        $this->assertEquals('1.5209', $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
    }
}
