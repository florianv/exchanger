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

use Exchanger\CurrencyPair;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\FawazahmedCurrencyApi;

class FawazahmedCurrencyApiTest extends ServiceTestCase
{
    public function testItHasAName(): void
    {
        $service = new FawazahmedCurrencyApi($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('fawazahmed_currency_api', $service->getName());
    }

    public function testSupportQuery(): void
    {
        $service = new FawazahmedCurrencyApi($this->createMock('Http\Client\HttpClient'));
        $pair = CurrencyPair::createFromString('EUR/USD');

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery($pair)));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery($pair, new \DateTime('2024-03-02'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery($pair, new \DateTime('2024-03-01'))));
    }

    public function testGetLatestExchangeRate(): void
    {
        $url = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/eur.min.json';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/FawazahmedCurrencyApi/EUR_latest.json');

        $pair = CurrencyPair::createFromString('EUR/USD');
        $service = new FawazahmedCurrencyApi($this->getHttpAdapterMock($url, $content));

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(1.16620516, $rate->getValue());
        $this->assertEquals(new \DateTime('2025-09-04'), $rate->getDate());
    }

    public function testGetHistoricExchangeRate(): void
    {
        $url = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@2025-01-01/v1/currencies/eur.min.json';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/FawazahmedCurrencyApi/EUR_historic.json');

        $pair = CurrencyPair::createFromString('EUR/USD');
        $service = new FawazahmedCurrencyApi($this->getHttpAdapterMock($url, $content));

        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('2025-01-01')));

        $this->assertSame(1.03544921, $rate->getValue());
        $this->assertEquals(new \DateTime('2025-01-01'), $rate->getDate());
    }

    public function testItThrowsAnExceptionWhenThePairIsNotSupported(): void
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $url = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/eur.min.json';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/FawazahmedCurrencyApi/EUR_latest.json');

        $pair = CurrencyPair::createFromString('EUR/XYZ');
        $service = new FawazahmedCurrencyApi($this->getHttpAdapterMock($url, $content));

        $service->getExchangeRate(new ExchangeRateQuery($pair));
    }

    public function testItThrowsAnExceptionWhenTheDateIsNotSupported(): void
    {
        $this->expectException(UnsupportedDateException::class);

        $url = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@1999-01-01/v1/currencies/eur.min.json';
        $content = "Couldn't find the requested release version 1999-01-01.";

        $pair = CurrencyPair::createFromString('EUR/USD');
        $service = new FawazahmedCurrencyApi($this->getHttpAdapterMock($url, $content, 404));

        $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('1999-01-01')));
    }
}
