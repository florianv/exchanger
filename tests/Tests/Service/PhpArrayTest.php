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

use Exchanger\ExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\PhpArray;
use PHPUnit\Framework\TestCase;

class PhpArrayTest extends TestCase
{
    /**
     * @test
     */
    public function it_supports_latest_queries()
    {
        $service = new PhpArray([]);
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));

        $service = new PhpArray(['EUR/USD' => 1, 'EUR/GBP' => 2.0]);
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
                'EUR/GBP' => 2.0,
            ],
        ]);

        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), $now)));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/GBP'), $now)));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('USD/GBP'), $now)));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_fetching_latest_invalid_rate()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rates passed to the PhpArray service must be scalars, "array" given.');

        $arrayProvider = new PhpArray([
            'EUR/USD' => [],
        ]);
    }

    /**
     * @test
     */
    public function it_fetches_a_latest_rate_from_rates()
    {
        $pair = CurrencyPair::createFromString('EUR/USD');

        $arrayProvider = new PhpArray([
            'EUR/USD' => $rate = 1.50,
        ]);

        $rate = $arrayProvider->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(1.50, $rate->getValue());
        $this->assertSame($pair, $rate->getCurrencyPair());
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

        $eurUsdPair = CurrencyPair::createFromString('EUR/USD');
        $usdGbpPair = CurrencyPair::createFromString('USD/GBP');
        $jpyGbpPair = CurrencyPair::createFromString('JPY/GBP');

        $eurUsd = $arrayProvider->getExchangeRate(new ExchangeRateQuery($eurUsdPair));
        $usdGbp = $arrayProvider->getExchangeRate(new ExchangeRateQuery($usdGbpPair));
        $jpyGbp = $arrayProvider->getExchangeRate(new ExchangeRateQuery($jpyGbpPair));

        $this->assertSame(1.5, $eurUsd->getValue());
        $this->assertSame(1.25, $usdGbp->getValue());
        $this->assertSame(1.0, $jpyGbp->getValue());

        $this->assertEquals('array', $eurUsd->getProviderName());
        $this->assertEquals('array', $usdGbp->getProviderName());
        $this->assertEquals('array', $jpyGbp->getProviderName());

        $this->assertSame($eurUsdPair, $eurUsd->getCurrencyPair());
        $this->assertSame($usdGbpPair, $usdGbp->getCurrencyPair());
        $this->assertSame($jpyGbpPair, $jpyGbp->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_fetching_historical_invalid_rate()
    {
        $this->expectException(\InvalidArgumentException::class);
        $expectedExceptionMessage = 'Rates passed to the PhpArray service must be scalars, "array" given.';
        $this->expectExceptionMessage($expectedExceptionMessage);

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
                'EUR/USD' => 1.50,
            ],
        ]);

        $rate = $arrayProvider->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), $now));

        $this->assertSame(1.50, $rate->getValue());
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

        $this->assertSame(1.5, $eurUsd->getValue());
        $this->assertSame(1.25, $usdGbp->getValue());
        $this->assertSame(1.0, $jpyGbp->getValue());

        $this->assertEquals('array', $eurUsd->getProviderName());
        $this->assertEquals('array', $usdGbp->getProviderName());
        $this->assertEquals('array', $jpyGbp->getProviderName());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new PhpArray([], []);

        $this->assertSame('array', $service->getName());
    }
}
