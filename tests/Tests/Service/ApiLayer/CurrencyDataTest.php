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
use Exchanger\Exception\NonBreakingInvalidArgumentException;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\ApiLayer\CurrencyData;

/**
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class CurrencyDataTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_throws_an_exception_if_api_key_option_missing()
    {
        $this->expectException(NonBreakingInvalidArgumentException::class);
        $this->expectExceptionMessage('The "api_key" option must be provided to use CurrencyData (https://currencylayer.com).');
        new CurrencyData($this->createMock('Http\Client\HttpClient'));
    }

    /**
     * @test
     */
    public function it_supports_all_queries()
    {
        $service = new CurrencyData($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/EUR'))));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_with_error_response()
    {
        $this->expectException(Exception::class);
        $uri = 'https://api.apilayer.com/currency_data/live?apikey=secret&currencies=EUR';
        $content = file_get_contents(__DIR__.'/../../../../Fixtures/Service/CurrencyData/error.json');

        $service = new CurrencyData($this->getHttpAdapterMock($uri, $content), null, ['api_key' => 'secret']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $uri = 'https://api.apilayer.com/currency_data/live?apikey=secret&currencies=EUR';
        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp(1399748450);
        $content = file_get_contents(__DIR__.'/../../../Fixtures/Service/CurrencyData/success.json');

        $pair = CurrencyPair::createFromString('USD/EUR');
        $service = new CurrencyData($this->getHttpAdapterMock($uri, $content), null, ['api_key' => 'secret']);
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertEquals(0.726804, $rate->getValue());
        $this->assertEquals($expectedDate, $rate->getDate());
        $this->assertEquals('apilayer_currency_data', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('USD/AED');
        $uri = 'http://apilayer.net/api/historical?apikey=secret&date=2015-05-06';
        $content = file_get_contents(__DIR__.'/../../../Fixtures/Service/CurrencyData/historical_success.json');
        $date = new \DateTime('2015-05-06');
        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp(1430870399);

        $service = new CurrencyData($this->getHttpAdapterMock($uri, $content), null, ['api_key' => 'secret']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEquals(3.673069, $rate->getValue());
        $this->assertEquals($expectedDate, $rate->getDate());
        $this->assertEquals('apilayer_currency_data', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new CurrencyData($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertSame('apilayer_currency_data', $service->getName());
    }
}
