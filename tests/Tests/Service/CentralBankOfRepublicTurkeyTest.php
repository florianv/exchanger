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

use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\CentralBankOfRepublicTurkey;

class CentralBankOfRepublicTurkeyTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new CentralBankOfRepublicTurkey($this->getMock('Http\Client\HttpClient'));

        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('TRY/EUR'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/GBP'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('XXX/TRY'), new \DateTime())));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $url = 'http://www.tcmb.gov.tr/kurlar/today.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CentralBankOfRepublicTurkey/cbrt_today.xml');

        $service = new CentralBankOfRepublicTurkey($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/TRY')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $url = 'http://www.tcmb.gov.tr/kurlar/today.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CentralBankOfRepublicTurkey/cbrt_today.xml');

        $service = new CentralBankOfRepublicTurkey($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/TRY')));

        $this->assertSame('3.2083', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-03-15'), $rate->getDate());
    }
}
