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
use Exchanger\Service\FastForex;

class FastForexTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new FastForex($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_rate_not_supported()
    {
        $this->expectException(Exception::class);
        $url = 'https://api.fastforex.io/fetch-one?from=EUR&to=ZZZ&api_key=secret';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/FastForex/error-unsupported.json');
        $service = new FastForex($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/ZZZ')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_when_response_symbol_matches()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $url = 'https://api.fastforex.io/fetch-one?from=USD&to=EUR&api_key=secret';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/FastForex/one-usd-eur.json');
        $service = new FastForex($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));
        $this->assertSame(0.8258, $rate->getValue());
        $this->assertEquals('2021-02-16', $rate->getDate()->format('Y-m-d'));
        $this->assertEquals('fastforex', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_response_symbol_does_not_match()
    {
        $this->expectException(Exception::class);
        $url = 'https://api.fastforex.io/fetch-one?from=USD&to=AED&api_key=secret';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/FastForex/one-usd-eur.json');
        $service = new FastForex($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/AED')));
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new FastForex($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertSame('fastforex', $service->getName());
    }
}
