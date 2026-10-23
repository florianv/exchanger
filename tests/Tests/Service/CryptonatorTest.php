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
use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\Service\Cryptonator;
use PHPUnit\Framework\Attributes\Test;
use Http\Client\HttpClient;
use Exchanger\Tests\FileContents;

class CryptonatorTest extends ServiceTestCase
{
    #[Test]
    public function it_throws_an_exception_when_rate_not_supported()
    {
        $this->expectException(Exception::class);
        $uri = 'https://api.cryptonator.com/api/ticker/btc-isk';
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/Cryptonator/error.json');

        $service = new Cryptonator($this->getHttpAdapterMock($uri, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('BTC/ISK')));
    }

    #[Test]
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('BTC/USD');
        $url = 'https://api.cryptonator.com/api/ticker/btc-usd';
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/Cryptonator/success.json');

        $service = new Cryptonator($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(4194.86340277, $rate->getValue());
        $this->assertInstanceOf(\DateTime::class, $rate->getDate());
        $this->assertEquals('cryptonator', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_has_a_name()
    {
        $service = new Cryptonator($this->createMock(HttpClient::class));

        $this->assertSame('cryptonator', $service->getName());
    }
}
