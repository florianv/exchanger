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

namespace Exchanger\Tests;

use Exchanger\CurrencyPair;
use Exchanger\HistoricalExchangeRateQuery;
use PHPUnit\Framework\TestCase;

class HistoricalExchangeRateQueryTest extends TestCase
{
    /**
     * @test
     */
    public function it_serializes()
    {
        $firstQuery = new HistoricalExchangeRateQuery(
            CurrencyPair::createFromString('EUR/USD'),
            new \DateTime('yesterday'),
            ['cache_ttl' => 3600]
        );

        $secondQuery = new HistoricalExchangeRateQuery(
            CurrencyPair::createFromString('EUR/USD'),
            new \DateTime('yesterday'),
            ['cache_ttl' => 3600]
        );

        $thirdQuery = new HistoricalExchangeRateQuery(
            CurrencyPair::createFromString('EUR/USD'),
            new \DateTime(),
            ['cache_ttl' => 3600]
        );

        $this->assertEquals(serialize($firstQuery), serialize($secondQuery));
        $this->assertNotEquals(serialize($secondQuery), serialize($thirdQuery));
    }
}
