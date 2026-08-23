<?php

declare(strict_types=1);


namespace Exchanger\Tests\Service;

use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\FawazahmedCurrencyAPI;

class FawazahmedCurrencyAPITest extends ServiceTestCase
{
    public function testItHasAName(): void
    {
        $service = new FawazahmedCurrencyAPI($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('fawazahmed_currency_api', $service->getName());
    }

    public function testGetLatestExchangeRate(): void
    {
        $url = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/eur.min.json';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/FawazahmedCurrencyAPI/EUR_latest.json');

        $pair = CurrencyPair::createFromString('EUR/USD');
        $service = new FawazahmedCurrencyAPI($this->getHttpAdapterMock($url, $content));

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(1.16620516, $rate->getValue());
        $this->assertEquals(new \DateTime('2025-09-04'), $rate->getDate());
    }

    public function testGetHistoricExchangeRate(): void
    {
        $url = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@2025-01-01/v1/currencies/eur.min.json';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/FawazahmedCurrencyAPI/EUR_historic.json');

        $pair = CurrencyPair::createFromString('EUR/USD');
        $service = new FawazahmedCurrencyAPI($this->getHttpAdapterMock($url, $content));

        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('2025-01-01')));

        $this->assertSame(1.03544921, $rate->getValue());
        $this->assertEquals(new \DateTime('2025-01-01'), $rate->getDate());
    }
}
