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
use Exchanger\Service\Yahoo;

class YahooTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new Yahoo($this->getMock('Http\Client\HttpClient'));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $url = 'https://query.yahooapis.com/v1/public/yql?q=select+%2A+from+yahoo.finance.xchange+where+pair+in+%28%22EURXXL%22%29&env=store://datatables.org/alltableswithkeys&format=json';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/YahooFinance/unsupported.json');

        $service = new Yahoo($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/XXL')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $url = 'https://query.yahooapis.com/v1/public/yql?q=select+%2A+from+yahoo.finance.xchange+where+pair+in+%28%22EURUSD%22%29&env=store://datatables.org/alltableswithkeys&format=json';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/YahooFinance/success.json');

        $service = new Yahoo($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD')));

        $this->assertSame('1.3758', $rate->getValue());
        $this->assertEquals(new \DateTime('2014-05-10 07:23:00'), $rate->getDate());
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     */
    public function it_throws_the_error_as_exception()
    {
        $url = 'https://query.yahooapis.com/v1/public/yql?q=select+%2A+from+yahoo.finance.xchange+where+pair+in+%28%22EURUSD%22%29&env=store://datatables.org/alltableswithkeys&format=json';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/YahooFinance/error.json');

        $service = new Yahoo($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD')));
    }
}
