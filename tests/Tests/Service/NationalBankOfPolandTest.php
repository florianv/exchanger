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

use Exchanger\CurrencyPair;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\NationalBankOfPoland;
use Http\Client\HttpClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class NationalBankOfPolandTest extends ServiceTestCase
{
    private const LATEST_URL = 'https://api.nbp.pl/api/exchangerates/rates/a/eur/?format=json';

    private const HISTORICAL_URL = 'https://api.nbp.pl/api/exchangerates/rates/a/eur/2020-07-15/?format=json';

    private const TABLE_B_URL = 'https://api.nbp.pl/api/exchangerates/rates/b/vnd/?format=json';

    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new NationalBankOfPoland($this->createMock(HttpClient::class));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/PLN'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('PLN/EUR'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('PLN/PLN'))));
    }

    /**
     * @test
     */
    public function it_does_not_support_dates_out_of_range()
    {
        $service = new NationalBankOfPoland($this->createMock(HttpClient::class));
        $pair = CurrencyPair::createFromString('EUR/PLN');

        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery($pair, new \DateTime('2002-01-02'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery($pair, new \DateTime('2002-01-01'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery($pair, new \DateTime('+1 day'))));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_table_is_not_supported()
    {
        $this->expectException(\InvalidArgumentException::class);

        new NationalBankOfPoland($this->createMock(HttpClient::class), null, ['table' => 'd']);
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/PLN');
        $service = new NationalBankOfPoland($this->getResponseMock(self::LATEST_URL, 'latest.json'));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(4.3124, $rate->getValue());
        $this->assertEquals(new \DateTime('2026-08-24'), $rate->getDate());
        $this->assertSame('narodowy_bank_polski', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_reversed_rate()
    {
        $pair = CurrencyPair::createFromString('PLN/EUR');
        $service = new NationalBankOfPoland($this->getResponseMock(self::LATEST_URL, 'latest.json'));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(1 / 4.3124, $rate->getValue());
        $this->assertEquals(new \DateTime('2026-08-24'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_from_the_table_b()
    {
        $pair = CurrencyPair::createFromString('VND/PLN');
        $service = new NationalBankOfPoland(
            $this->getResponseMock(self::TABLE_B_URL, 'latest_table_b.json'),
            null,
            ['table' => 'B']
        );
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.00014252, $rate->getValue());
        $this->assertEquals(new \DateTime('2026-08-19'), $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/PLN');
        $service = new NationalBankOfPoland($this->getResponseMock(self::HISTORICAL_URL, 'historical.json'));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('2020-07-15')));

        $this->assertSame(4.4738, $rate->getValue());
        $this->assertEquals(new \DateTime('2020-07-15'), $rate->getDate());
        $this->assertSame('narodowy_bank_polski', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $url = 'https://api.nbp.pl/api/exchangerates/rates/a/xxx/?format=json';
        $service = new NationalBankOfPoland($this->getResponseMock($url, null, 404));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/PLN')));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_date_is_not_supported()
    {
        $this->expectException(UnsupportedDateException::class);

        $url = 'https://api.nbp.pl/api/exchangerates/rates/a/eur/2020-07-18/?format=json';
        $service = new NationalBankOfPoland($this->getResponseMock($url, null, 404));
        $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/PLN'), new \DateTime('2020-07-18'))
        );
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new NationalBankOfPoland($this->createMock(HttpClient::class));

        $this->assertSame('narodowy_bank_polski', $service->getName());
    }

    /**
     * Creates a mocked http client returning the given fixture and status code.
     *
     * @param string      $url        The expected url
     * @param string|null $fixture    The fixture file name, null for an error response
     * @param int         $statusCode The http status code
     *
     * @return HttpClient
     */
    private function getResponseMock(string $url, ?string $fixture, int $statusCode = 200): HttpClient
    {
        $content = null !== $fixture
            ? file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfPoland/'.$fixture)
            : '404 NotFound - Not Found - Brak danych';

        $body = $this->createMock(StreamInterface::class);
        $body
            ->method('__toString')
            ->willReturn($content);

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('getBody')
            ->willReturn($body);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $adapter = $this->createMock(HttpClient::class);
        $adapter
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function ($arg) use ($url) {
                return $arg->getUri()->__toString() === $url;
            }))
            ->willReturn($response);

        return $adapter;
    }
}
