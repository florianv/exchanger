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

use Exchanger\ExchangeRateQueryBuilder;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ExchangeRateQueryBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_a_rate()
    {
        $builder = new ExchangeRateQueryBuilder('EUR/USD');
        $this->assertInstanceOf(ExchangeRateQuery:: class, $builder->build());
    }

    #[Test]
    public function it_builds_an_historical_rate()
    {
        $builder = (new ExchangeRateQueryBuilder('EUR/USD'))
            ->setDate(new \DateTime());

        $this->assertInstanceOf(HistoricalExchangeRateQuery:: class, $builder->build());
    }
}
