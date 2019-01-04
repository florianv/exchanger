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

use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\Service\Forge;

class ForgeTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new Forge($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     */
    public function it_throws_an_exception_when_rate_not_supported()
    {
        $url = 'https://forex.1forge.com/latest/quotes?pairs=EURZZZ&api_key=secret';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Forge/error.json');
        $service = new Forge($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/ZZZ')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/USD');
        $url = 'https://forex.1forge.com/latest/quotes?pairs=EURUSD&api_key=secret';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Forge/success.json');
        $service = new Forge($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(1.18711, $rate->getValue());
        $this->assertTrue('2017-12-21' == $rate->getDate()->format('Y-m-d'));
        $this->assertEquals('forge', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_when_response_symbol_matches()
    {
        $pair = CurrencyPair::createFromString('EUR/HKD');
        $url = 'https://forex.1forge.com/latest/quotes?pairs=EURHKD&api_key=secret';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Forge/multiple.json');
        $service = new Forge($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));
        $this->assertSame(9.12721, $rate->getValue());
        $this->assertTrue('2018-05-30' == $rate->getDate()->format('Y-m-d'));
        $this->assertEquals('forge', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     */
    public function it_throws_an_exception_when_response_symbol_does_not_match()
    {
        $url = 'https://forex.1forge.com/latest/quotes?pairs=USDAED&api_key=secret';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Forge/multiple.json');
        $service = new Forge($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/AED')));
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new Forge($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertSame('forge', $service->getName());
    }
}
