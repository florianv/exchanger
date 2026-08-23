<?php

namespace Exchanger\Tests\Service;

use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\Frankfurter;

class FrankfurterTest extends ServiceTestCase
{
    public function testItHasAName(): void
    {
        $service = new Frankfurter($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('frankfurter', $service->getName());
    }

    public function testFetchLatestRate(): void
    {
        $url = 'https://api.frankfurter.dev/v1/latest?base=EUR&symbols=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Frankfurter/EUR_USD.json');

        $pair = CurrencyPair::createFromString('EUR/USD');
        $service = new Frankfurter($this->getHttpAdapterMock($url, $content));

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(1.1697, $rate->getValue());
        $this->assertEquals(new \DateTime('2025-09-05'), $rate->getDate());
    }

    public function testFetchHistoricRate(): void
    {
        $url = 'https://api.frankfurter.dev/v1/1999-04-13?base=EUR&symbols=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Frankfurter/EUR_USD_historic.json');

        $pair = CurrencyPair::createFromString('EUR/USD');
        $service = new Frankfurter($this->getHttpAdapterMock($url, $content));

        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime("1999-04-13")));

        $this->assertSame(1.0765, $rate->getValue());
        $this->assertEquals(new \DateTime('1999-04-13'), $rate->getDate());
    }
}
