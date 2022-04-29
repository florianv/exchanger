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

use Exchanger\Exception\Exception;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\ExchangerateHost;

/**
 * @author Pascal Hofmann <mail@pascalhofmann.de>
 */
class ExchangerateHostTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_supports_all_queries()
    {
        $service = new ExchangerateHost($this->createMock('Http\Client\HttpClient'));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_with_error_response()
    {
        $this->expectException(Exception::class);
        $expectedExceptionMessage = '';
        $this->expectExceptionMessage($expectedExceptionMessage);

        $uri = 'https://api.exchangerate.host/latest?base=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/ExchangerateHost/error.json');

        $service = new ExchangerateHost($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'x']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/CHF');
        $uri = 'https://api.exchangerate.host/latest?base=EUR';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/ExchangerateHost/latest.json');

        $service = new ExchangerateHost($this->getHttpAdapterMock($uri, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertEquals(1.021468, $rate->getValue());
        $this->assertEquals(new \DateTime('2022-04-29'), $rate->getDate());
        $this->assertEquals('exchangeratehost', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/AUD');
        $uri = 'https://api.exchangerate.host/2000-01-03?base=EUR';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/ExchangerateHost/historical.json');
        $date = new \DateTime('2000-01-03');

        $service = new ExchangerateHost($this->getHttpAdapterMock($uri, $content));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEquals(1.5346, $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('exchangeratehost', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new ExchangerateHost($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('exchangeratehost', $service->getName());
    }
}
