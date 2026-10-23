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
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Client\ClientInterface;
use Exchanger\Tests\FileContents;

final class WebserviceXTest extends ServiceTestCase
{
    #[Test]
    public function it_does_not_support_all_queries(): void
    {
        $service = new WebserviceX($this->createMock(ClientInterface::class));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    #[Test]
    public function it_fetches_a_rate(): void
    {
        $pair = CurrencyPair::createFromString('EUR/USD');
        $uri = 'http://www.webservicex.net/currencyconvertor.asmx/ConversionRate?FromCurrency=EUR&ToCurrency=USD';
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/WebserviceX/success.xml');

        $service = new WebserviceX($this->getHttpAdapterMock($uri, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertEquals(1.3608, $rate->getValue());
        $this->assertEquals((new \DateTime())->format('Y-m-d'), $rate->getDate()->format('Y-m-d'));
        $this->assertEquals('webservicex', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_has_a_name(): void
    {
        $service = new WebserviceX($this->createMock(ClientInterface::class));

        $this->assertSame('webservicex', $service->getName());
    }
}
