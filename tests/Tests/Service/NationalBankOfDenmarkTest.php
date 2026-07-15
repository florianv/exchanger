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

use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\NationalBankOfDenmark;
use Http\Client\HttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

class NationalBankOfDenmarkTest extends ServiceTestCase
{
    #[Test]
    #[DataProvider('getSupportedCurrencies')]
    public function it_does_not_support_all_queries(string $currency): void
    {
        $service = new NationalBankOfDenmark($this->createMock(HttpClient::class));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString($currency . '/DKK'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('DKK/' . $currency))));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString($currency . '/DKK'), new \DateTime())));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('DKK/' . $currency), new \DateTime())));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('AMD/' . $currency))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString($currency . '/AMD'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('AMD/' . $currency), new \DateTime())));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString($currency . '/AMD'), new \DateTime())));
    }

    #[Test]
    public function it_throws_an_exception_when_the_pair_is_not_supported(): void
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/NationalBankOfDenmark/currencyratesxml.xml');

        $service = new NationalBankOfDenmark($this->getHttpAdapterMock(NationalBankOfDenmark::LATEST_URL, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/DKK')));
    }

    #[Test]
    public function it_fetches_a_rate(): void
    {
        $pair = CurrencyPair::createFromString('EUR/DKK');
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/NationalBankOfDenmark/currencyratesxml.xml');

        $service = new NationalBankOfDenmark($this->getHttpAdapterMock(NationalBankOfDenmark::LATEST_URL, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(7.475, $rate->getValue());
        $this->assertEquals(new \DateTime('2026-07-13'), $rate->getDate());
        $this->assertEquals('national_bank_of_denmark', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_a_rate_when_dkk_is_base(): void
    {
        $pair = CurrencyPair::createFromString('DKK/EUR');
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/NationalBankOfDenmark/currencyratesxml.xml');

        $service = new NationalBankOfDenmark($this->getHttpAdapterMock(NationalBankOfDenmark::LATEST_URL, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.133779, $rate->getValue());
        $this->assertEquals(new \DateTime('2026-07-13'), $rate->getDate());
        $this->assertEquals('national_bank_of_denmark', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_a_recent_historical_rate(): void
    {
        $pair = CurrencyPair::createFromString('EUR/DKK');
        $date = new \DateTime('-2 days');
        $content = $this->buildFiveDayHistoryContent($date->format('Y-m-d'));

        $service = new NationalBankOfDenmark($this->getHttpAdapterMock(NationalBankOfDenmark::FIVE_DAY_HISTORY_URL, $content));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEqualsWithDelta(7.4746, $rate->getValue(), 1e-9);
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('national_bank_of_denmark', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_throws_an_exception_when_recent_historical_date_is_missing(): void
    {
        $this->expectException(UnsupportedDateException::class);

        $date = new \DateTime('-1 days');
        $content = $this->buildFiveDayHistoryContent((new \DateTime('-2 days'))->format('Y-m-d'));

        $service = new NationalBankOfDenmark($this->getHttpAdapterMock(NationalBankOfDenmark::FIVE_DAY_HISTORY_URL, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/DKK'), $date));
    }

    #[Test]
    public function it_throws_an_exception_when_the_pair_is_not_supported_for_a_recent_historical_date(): void
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $date = new \DateTime('-2 days');
        $content = $this->buildFiveDayHistoryContent($date->format('Y-m-d'));

        $service = new NationalBankOfDenmark($this->getHttpAdapterMock(NationalBankOfDenmark::FIVE_DAY_HISTORY_URL, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('XXX/DKK'), $date));
    }

    #[Test]
    public function it_fetches_an_old_historical_rate(): void
    {
        $pair = CurrencyPair::createFromString('EUR/DKK');
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/NationalBankOfDenmark/statbank-eur.json');

        $service = new NationalBankOfDenmark($this->getStatbankAdapterMock($content, 'EUR', '2024M03D14'));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('2024-03-14')));

        $this->assertEqualsWithDelta(7.4568, $rate->getValue(), 1e-9);
        $this->assertEquals(new \DateTime('2024-03-14'), $rate->getDate());
        $this->assertEquals('national_bank_of_denmark', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_an_old_historical_rate_when_dkk_is_base(): void
    {
        $pair = CurrencyPair::createFromString('DKK/EUR');
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/NationalBankOfDenmark/statbank-eur.json');

        $service = new NationalBankOfDenmark($this->getStatbankAdapterMock($content, 'EUR', '2024M03D14'));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('2024-03-14')));

        $this->assertSame(0.134106, $rate->getValue());
        $this->assertEquals(new \DateTime('2024-03-14'), $rate->getDate());
        $this->assertEquals('national_bank_of_denmark', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_throws_an_exception_when_old_historical_date_is_missing(): void
    {
        $this->expectException(UnsupportedDateException::class);
        $this->expectExceptionMessage("The date \"2024-03-16\" is not supported by the service \"Exchanger\Service\NationalBankOfDenmark\".");

        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/NationalBankOfDenmark/statbank-notfound-date.json');

        $service = new NationalBankOfDenmark($this->getStatbankAdapterMock($content, 'EUR', '2024M03D16'));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/DKK'), new \DateTime('2024-03-16')));
    }

    #[Test]
    public function it_posts_to_statbank_using_the_injected_stream_factory(): void
    {
        $pair = CurrencyPair::createFromString('EUR/DKK');
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/NationalBankOfDenmark/statbank-eur.json');
        $expectedBody = $this->buildStatbankRequestBody('EUR', '2024M03D14');

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory
            ->expects($this->once())
            ->method('createStream')
            ->with($expectedBody)
            ->willReturn((new Psr17Factory())->createStream($expectedBody));

        $service = new NationalBankOfDenmark(
            $this->getStatbankAdapterMock($content, 'EUR', '2024M03D14'),
            new Psr17Factory(),
            [],
            $streamFactory
        );
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('2024-03-14')));

        $this->assertEqualsWithDelta(7.4568, $rate->getValue(), 1e-9);
    }

    #[Test]
    public function it_has_a_name(): void
    {
        $service = new NationalBankOfDenmark($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('national_bank_of_denmark', $service->getName());
    }

    public static function getSupportedCurrencies(): array
    {
        $currencies = (new \ReflectionClassConstant(NationalBankOfDenmark::class, 'SUPPORTED_CURRENCIES'))->getValue();

        return array_map(static fn(string $currency): array => [$currency], $currencies);
    }

    /**
     * Creates an adapter mock for the StatBank endpoint, checking that the request
     * is a POST with a JSON content type and the expected DNVALD query body.
     *
     * @return \Http\Client\HttpClient
     */
    private function getStatbankAdapterMock(string $content, string $currency, string $tid)
    {
        $expectedBody = $this->buildStatbankRequestBody($currency, $tid);

        return $this->getHttpAdapterMock(
            NationalBankOfDenmark::STATBANK_URL,
            $content,
            200,
            function (RequestInterface $request) use ($expectedBody): bool {
                return 'POST' === $request->getMethod()
                    && 'application/json' === $request->getHeaderLine('Content-Type')
                    && $expectedBody === (string) $request->getBody();
            }
        );
    }

    /**
     * Builds the JSON body the service is expected to post to StatBank.
     */
    private function buildStatbankRequestBody(string $currency, string $tid): string
    {
        return (string) json_encode([
            'table' => 'DNVALD',
            'format' => 'JSONSTAT',
            'lang' => 'en',
            'variables' => [
                ['code' => 'VALUTA', 'values' => [$currency]],
                ['code' => 'KURTYP', 'values' => ['KBH']],
                ['code' => 'Tid', 'values' => [$tid]],
            ],
        ]);
    }

    /**
     * Builds a five day history feed containing a single day of rates.
     */
    private function buildFiveDayHistoryContent(string $formattedDate): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<gesmes:Envelope xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref" xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01">'
            . '<gesmes:subject>Exchange rates</gesmes:subject>'
            . '<gesmes:Sender><gesmes:name>Danmarks Nationalbank</gesmes:name></gesmes:Sender>'
            . '<Cube><Cube time="' . $formattedDate . '">'
            . '<Cube rate="747.46" currency="EUR" name="Euro" />'
            . '<Cube rate="653.77" currency="USD" name="US dollars" />'
            . '</Cube></Cube></gesmes:Envelope>';
    }
}
