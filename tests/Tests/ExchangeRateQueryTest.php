<?php

/*
 * This file is part of Exchanger.
 *
 * (c) Florian Voutzinos <florian@voutzinos.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Tests;

use Exchanger\ExchangeRateQuery;
use Exchanger\CurrencyPair;

class ExchangeRateQueryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_serializes()
    {
        $firstQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));
        $secondQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));
        $thirdQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), ['cache_ttl' => 3600]);
        $fourthQuery = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), ['cache_ttl' => 3600]);

        $this->assertEquals(serialize($firstQuery), serialize($secondQuery));
        $this->assertNotEquals(serialize($thirdQuery), serialize($firstQuery));
        $this->assertEquals($thirdQuery, $fourthQuery);
    }
}
