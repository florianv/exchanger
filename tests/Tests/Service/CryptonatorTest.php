<?php

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
use Exchanger\Service\Cryptonator;

class CryptonatorTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_codes()
    {
        $service = new Cryptonator($this->createMock('Http\Client\HttpClient'));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('NONCODE/NONCODE'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('BTC/USD'))));
    }

    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new Cryptonator($this->createMock('Http\Client\HttpClient'));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('BTC/USD'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('BTC/USD'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     */
    public function it_throws_an_exception_when_rate_not_supported()
    {
        $uri = 'https://api.cryptonator.com/api/ticker/btc-isk';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Cryptonator/error.json');

        $service = new Cryptonator($this->getHttpAdapterMock($uri, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('BTC/ISK')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $url = 'https://api.cryptonator.com/api/ticker/btc-usd';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Cryptonator/success.json');

        $service = new Cryptonator($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('BTC/USD')));

        $this->assertSame(4194.86340277, $rate->getValue());
        $this->assertInstanceOf('\DateTime', $rate->getDate());
        $this->assertEquals(Cryptonator::class, $rate->getProvider());
    }
}
