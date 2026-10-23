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

use Exchanger\CurrencyPair;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\Frankfurter;
use PHPUnit\Framework\Attributes\Test;

class FrankfurterTest extends ServiceTestCase
{
    #[Test]
    public function it_has_a_name(): void
    {
        $service = new Frankfurter($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('frankfurter', $service->getName());
    }

    #[Test]
    public function it_does_not_support_dates_before_the_series_start(): void
    {
        $service = new Frankfurter($this->createMock('Http\Client\HttpClient'));
        $pair = CurrencyPair::createFromString('EUR/USD');

        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery($pair, new \DateTime('1990-01-01'))));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery($pair, new \DateTime('1999-01-04'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery($pair)));
    }

    #[Test]
    public function it_fetches_latest_rate(): void
    {
        $url = 'https://api.frankfurter.dev/v2/rates?base=EUR&quotes=USD';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/Frankfurter/EUR_USD.json');

        $pair = CurrencyPair::createFromString('EUR/USD');
        $service = new Frankfurter($this->getHttpAdapterMock($url, $content));

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(1.1697, $rate->getValue());
        $this->assertEquals(new \DateTime('2025-09-05'), $rate->getDate());
    }

    #[Test]
    public function it_fetches_historic_rate(): void
    {
        $url = 'https://api.frankfurter.dev/v2/rates?base=EUR&quotes=USD&date=1999-04-13';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/Frankfurter/EUR_USD_historic.json');

        $pair = CurrencyPair::createFromString('EUR/USD');
        $service = new Frankfurter($this->getHttpAdapterMock($url, $content));

        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('1999-04-13')));

        $this->assertSame(1.0765, $rate->getValue());
        $this->assertEquals(new \DateTime('1999-04-13'), $rate->getDate());
    }

    #[Test]
    public function it_throws_an_exception_when_the_pair_is_not_supported(): void
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $url = 'https://api.frankfurter.dev/v2/rates?base=EUR&quotes=ZZZ';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/Frankfurter/error_invalid_currency.json');

        $pair = CurrencyPair::createFromString('EUR/ZZZ');
        $service = new Frankfurter($this->getHttpAdapterMock($url, $content, 422));

        $service->getExchangeRate(new ExchangeRateQuery($pair));
    }

    #[Test]
    public function it_throws_an_exception_when_the_historical_date_is_missing(): void
    {
        $this->expectException(UnsupportedDateException::class);

        $url = 'https://api.frankfurter.dev/v2/rates?base=EUR&quotes=USD&date=1999-01-01';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/Frankfurter/empty.json');

        $pair = CurrencyPair::createFromString('EUR/USD');
        $service = new Frankfurter($this->getHttpAdapterMock($url, $content));

        $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('1999-01-01')));
    }
}
