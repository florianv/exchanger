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

namespace Exchanger\Tests\Service\ApiLayer;

use Exchanger\Exception\Exception;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\ApiLayer\Fixer;
use Exchanger\Tests\Service\ServiceTestCase;

/**
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class FixerTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_throws_an_exception_if_api_key_option_missing()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "api_key" option must be provided to use Fixer (https://apilayer.com/marketplace/fixer-api).');
        new Fixer($this->createMock('Http\Client\HttpClient'));
    }

    /**
     * @test
     */
    public function it_supports_all_queries()
    {
        $service = new Fixer($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'x']);
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_with_error_response()
    {
        $this->expectException(Exception::class);
        $expectedExceptionMessage = 'The current subscription plan does not support this API endpoint.';
        $this->expectExceptionMessage($expectedExceptionMessage);

        $uri = 'https://api.apilayer.com/fixer/latest?base=USD&apikey=x';
        $content = file_get_contents(__DIR__.'/../../../Fixtures/Service/ApiLayer/Fixer/error.json');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['api_key' => 'x']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/CHF');
        $uri = 'https://api.apilayer.com/fixer/latest?base=EUR&apikey=x';
        $content = file_get_contents(__DIR__.'/../../../Fixtures/Service/ApiLayer/Fixer/latest.json');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['api_key' => 'x']);
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertEquals(1.0933, $rate->getValue());
        $this->assertEquals(new \DateTime('2016-08-26'), $rate->getDate());
        $this->assertEquals('apilayer_fixer', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/AUD');
        $uri = 'https://api.apilayer.com/fixer/2000-01-03?base=EUR&apikey=x';
        $content = file_get_contents(__DIR__.'/../../../Fixtures/Service/ApiLayer/Fixer/historical.json');
        $date = new \DateTime('2000-01-03');

        $service = new Fixer($this->getHttpAdapterMock($uri, $content), null, ['api_key' => 'x']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEquals(1.5209, $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('apilayer_fixer', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new Fixer($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'x']);

        $this->assertSame('apilayer_fixer', $service->getName());
    }
}
