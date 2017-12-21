<?php

namespace Exchanger\Tests\Service;

use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\Service\Forge;

class ForgeTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_codes()
    {
        $service = new Forge($this->getMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('NONCODE/NONCODE'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
    }

    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new Forge($this->getMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     */
    public function it_throws_an_exception_when_rate_not_supported()
    {
        $uri = 'https://forex.1forge.com/latest/quotes?pairs=EURUSD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Forge/error.json');

        $service = new Forge($this->getHttpAdapterMock($uri, $content), null, ['api_key' => 'secret']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/BTC')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $url = 'https://forex.1forge.com/latest/quotes?pairs=EURUSD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Forge/success.json');

        $service = new Forge($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD')));

        $this->assertSame('1.18711', $rate->getValue());
        $this->assertInstanceOf('\DateTime', $rate->getDate());
    }
}
