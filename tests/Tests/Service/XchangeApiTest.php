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

use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\XchangeApi;

/**
 * @author xChangeApi.com <hello@xchangeapi.com>
 */
class XchangeApiTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new XchangeApi($this->createMock('Http\Client\HttpClient'), null, ['api-key' => 'secret']);
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('ABC/DEF'))));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/GBP');
        $uri = 'https://api.xchangeapi.com/latest?base=EUR&api-key=secret';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/XchangeApi/latest.json');

        $service = new XchangeApi($this->getHttpAdapterMock($uri, $content), null, ['api-key' => 'secret']);
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertEquals(0.76257, $rate->getValue());
        $this->assertEquals(new \DateTime('2020-08-05T18:28:59'), $rate->getDate());
        $this->assertEquals('xchangeapi', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rates()
    {
        $pair = CurrencyPair::createFromString('USD/JPY');
        $uri = 'https://api.xchangeapi.com/historical/2020-01-20?api-key=secret';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/XchangeApi/historical.json');
        $date = new \DateTime('2020-01-20');

        $service = new XchangeApi($this->getHttpAdapterMock($uri, $content), null, ['api-key' => 'secret']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEquals(122.14, $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('xchangeapi', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new XchangeApi($this->createMock('Http\Client\HttpClient'), null, ['api-key' => 'secret']);

        $this->assertSame('xchangeapi', $service->getName());
    }
}
