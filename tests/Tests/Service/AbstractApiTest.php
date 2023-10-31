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
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\Service\AbstractApi;

class AbstractApiTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_supports_all_queries()
    {
        $service = new AbstractApi($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_rate_not_supported()
    {
        $this->expectException(Exception::class);
        $url = 'https://exchange-rates.abstractapi.com/v1/live/?api_key=secret&base=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/AbstractApi/success.json');
        $service = new AbstractApi($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/ZZZ')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('USD/GBP');
        $url = 'https://exchange-rates.abstractapi.com/v1/live/?api_key=secret&base=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/AbstractApi/success.json');
        $service = new AbstractApi($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.71008, $rate->getValue());
        $this->assertEquals('2021-05-14', $rate->getDate()->format('Y-m-d'));
        $this->assertEquals('abstract_api', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('USD/GBP');
        $url = 'https://exchange-rates.abstractapi.com/v1/historical?api_key=secret&base=USD&date=2000-01-03';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/AbstractApi/historical.json');
        $date = new \DateTime('2000-01-03');

        $service = new AbstractApi($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertSame(0.71008, $rate->getValue());
        $this->assertEquals('2021-05-14', $rate->getDate()->format('Y-m-d'));
        $this->assertEquals('abstract_api', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new AbstractApi($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertSame('abstract_api', $service->getName());
    }
}
