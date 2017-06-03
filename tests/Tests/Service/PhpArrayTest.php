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

use Exchanger\ExchangeRate;
use Exchanger\ExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\PhpArray;

class PhpArrayTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_supports_latest_queries()
    {
        $service = new PhpArray([]);
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));

        $service = new PhpArray(['EUR/USD' => 1, 'EUR/GBP' => new ExchangeRate(2)]);
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/GBP'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/GBP'))));
    }

    /**
     * @test
     */
    public function it_supports_historical_queries()
    {
        $now = new \DateTimeImmutable();

        $service = new PhpArray([], []);
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'), $now)));

        $service = new PhpArray([], [
            $now->format('Y-m-d') => [
                'EUR/USD' => 1,
                'EUR/GBP' => new ExchangeRate('2.0'),
            ],
        ]);

        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), $now)));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/GBP'), $now)));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('USD/GBP'), $now)));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\InternalException
     * @expectedExceptionMessage Rates passed to the PhpArray service must be Rate instances or scalars "array" given.
     */
    public function it_throws_an_exception_when_fetching_latest_invalid_rate()
    {
        $arrayProvider = new PhpArray([
            'EUR/USD' => [],
        ]);

        $arrayProvider->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD')));
    }

    /**
     * @test
     */
    public function it_fetches_a_latest_rate_from_rates()
    {
        $arrayProvider = new PhpArray([
            'EUR/USD' => $rate = new ExchangeRate('1.50'),
        ]);

        $this->assertSame($rate, $arrayProvider->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
    }

    /**
     * @test
     */
    public function it_fetches_a_latest_rate_from_scalars()
    {
        $arrayProvider = new PhpArray([
            'EUR/USD' => 1.50,
            'USD/GBP' => '1.25',
            'JPY/GBP' => 1,
        ]);

        $eurUsd = $arrayProvider->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD')));
        $usdGbp = $arrayProvider->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/GBP')));
        $jpyGbp = $arrayProvider->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('JPY/GBP')));

        $this->assertEquals('1.50', $eurUsd->getValue());
        $this->assertEquals('1.25', $usdGbp->getValue());
        $this->assertEquals('1', $jpyGbp->getValue());
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\InternalException
     * @expectedExceptionMessage Rates passed to the PhpArray service must be Rate instances or scalars "array" given.
     */
    public function it_throws_an_exception_when_fetching_historical_invalid_rate()
    {
        $now = new \DateTimeImmutable();

        $arrayProvider = new PhpArray([], [
            $now->format('Y-m-d') => [
                'EUR/USD' => [],
            ],
        ]);

        $arrayProvider->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), $now));
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate_from_exchange_rates()
    {
        $now = new \DateTimeImmutable();

        $arrayProvider = new PhpArray([], [
            $now->format('Y-m-d') => [
                'EUR/USD' => $rate = new ExchangeRate('1.50'),
            ],
        ]);

        $this->assertSame($rate, $arrayProvider->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), $now)));
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate_from_scalars()
    {
        $now = new \DateTimeImmutable();

        $arrayProvider = new PhpArray([], [
            $now->format('Y-m-d') => [
                'EUR/USD' => 1.50,
                'USD/GBP' => '1.25',
                'JPY/GBP' => 1,
            ],
        ]);

        $eurUsd = $arrayProvider->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), $now));
        $usdGbp = $arrayProvider->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('USD/GBP'), $now));
        $jpyGbp = $arrayProvider->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('JPY/GBP'), $now));

        $this->assertEquals('1.50', $eurUsd->getValue());
        $this->assertEquals('1.25', $usdGbp->getValue());
        $this->assertEquals('1', $jpyGbp->getValue());
    }
}
