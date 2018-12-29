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
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\WebserviceX;

class WebserviceXTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new WebserviceX($this->createMock('Http\Client\HttpClient'));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $uri = 'http://www.webservicex.net/currencyconvertor.asmx/ConversionRate?FromCurrency=EUR&ToCurrency=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/WebserviceX/success.xml');

        $service = new WebserviceX($this->getHttpAdapterMock($uri, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD')));

        $this->assertEquals(1.3608, $rate->getValue());
        $this->assertEquals((new \DateTime())->format('Y-m-d'), $rate->getDate()->format('Y-m-d'));
        $this->assertEquals(WebserviceX::class, $rate->getProvider());
    }
}
