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
use Exchanger\Service\PhpArray;

class PhpArrayTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new PhpArray([]);
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\InternalException
     * @expectedExceptionMessage Rates passed to the PhpArray service must be Rate instances or scalars "array" given.
     */
    public function it_throws_an_exception_when_fetching_invalid_rate()
    {
        $arrayProvider = new PhpArray([
            'EUR/USD' => [],
        ]);

        $arrayProvider->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_from_rates()
    {
        $arrayProvider = new PhpArray([
            'EUR/USD' => $rate = new ExchangeRate('1.50'),
        ]);

        $this->assertSame($rate, $arrayProvider->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_from_scalars()
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
}
