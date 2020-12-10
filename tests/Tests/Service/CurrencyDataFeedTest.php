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
use Exchanger\Service\CurrencyDataFeed;

class CurrencyDataFeedTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new CurrencyDataFeed($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_rate_not_supported()
    {
        $this->expectException(Exception::class);
        $url = 'https://currencydatafeed.com/api/data.php?token=secret&currency=EUR/ZZZ';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyDataFeed/error.json');
        $service = new CurrencyDataFeed($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/ZZZ')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/USD');
        $url = 'https://currencydatafeed.com/api/data.php?token=secret&currency=EUR/USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyDataFeed/success.json');
        $service = new CurrencyDataFeed($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(1.18765, $rate->getValue());
        $this->assertEquals('2017-12-21', $rate->getDate()->format('Y-m-d'));
        $this->assertEquals('currency_data_feed', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new CurrencyDataFeed($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertSame('currency_data_feed', $service->getName());
    }
}
