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
use Exchanger\Service\Cryptonator;

class CryptonatorTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_throws_an_exception_when_rate_not_supported()
    {
        $this->expectException(Exception::class);
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
        $pair = CurrencyPair::createFromString('BTC/USD');
        $url = 'https://api.cryptonator.com/api/ticker/btc-usd';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/Cryptonator/success.json');

        $service = new Cryptonator($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(4194.86340277, $rate->getValue());
        $this->assertInstanceOf('\DateTime', $rate->getDate());
        $this->assertEquals('cryptonator', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new Cryptonator($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('cryptonator', $service->getName());
    }
}
