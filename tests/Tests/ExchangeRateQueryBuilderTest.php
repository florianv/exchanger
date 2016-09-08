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

use Exchanger\ExchangeRateQueryBuilder;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;

class ExchangeRateQueryBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_builds_a_rate()
    {
        $builder = new ExchangeRateQueryBuilder('EUR/USD');
        $this->assertInstanceOf(ExchangeRateQuery:: class, $builder->build());
    }

    /**
     * @test
     */
    public function it_builds_an_historical_rate()
    {
        $builder = (new ExchangeRateQueryBuilder('EUR/USD'))
            ->setDate(new \DateTime());

        $this->assertInstanceOf(HistoricalExchangeRateQuery:: class, $builder->build());
    }
}
