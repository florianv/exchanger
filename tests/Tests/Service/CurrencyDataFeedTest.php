<?php

namespace Exchanger\Tests\Service;

use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\Service\CurrencyDataFeed;

class CurrencyDataFeedTest extends ServiceTestCase {

    /**
     * @test
     */
    public function it_does_not_support_all_queries() {
        $service = new CurrencyDataFeed($this->getMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     */
    public function it_throws_an_exception_when_rate_not_supported() {
        $url = 'https://currencydatafeed.com/api/data.php?token=secret&currency=EUR/ZZZ';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/CurrencyDataFeed/error.json');
        $service = new CurrencyDataFeed($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);
        
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/ZZZ')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate() {
        $url = 'https://currencydatafeed.com/api/data.php?token=secret&currency=EUR/USD';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/CurrencyDataFeed/success.json');
        $service = new CurrencyDataFeed($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD')));

        $this->assertSame('1.18765', $rate->getValue());
        $this->assertTrue('2017-12-21' == $rate->getDate()->format('Y-m-d'));
    }

}
