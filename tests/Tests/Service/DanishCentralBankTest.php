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
use Exchanger\Service\DanishCentralBank;
use Http\Client\HttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

class DanishCentralBankTest extends ServiceTestCase
{
    #[Test]
    #[DataProvider('getSupportedCurrencies')]
    public function it_does_not_support_all_queries(string $currency): void
    {
        $service = new DanishCentralBank($this->createMock(HttpClient::class));

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

        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/DanishCentralBank/currencyratesxml.xml');

        $service = new DanishCentralBank($this->getHttpAdapterMock(DanishCentralBank::LATEST_URL, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/DKK')));
    }

    #[Test]
    public function it_fetches_a_rate(): void
    {
        $pair = CurrencyPair::createFromString('EUR/DKK');
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/DanishCentralBank/currencyratesxml.xml');

        $service = new DanishCentralBank($this->getHttpAdapterMock(DanishCentralBank::LATEST_URL, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(7.475, $rate->getValue());
        $this->assertEquals(new \DateTime('2026-07-13'), $rate->getDate());
        $this->assertEquals('danish_central_bank', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_a_rate_when_dkk_is_base(): void
    {
        $pair = CurrencyPair::createFromString('DKK/EUR');
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/DanishCentralBank/currencyratesxml.xml');

        $service = new DanishCentralBank($this->getHttpAdapterMock(DanishCentralBank::LATEST_URL, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.133779, $rate->getValue());
        $this->assertEquals(new \DateTime('2026-07-13'), $rate->getDate());
        $this->assertEquals('danish_central_bank', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_a_recent_historical_rate(): void
    {
        $pair = CurrencyPair::createFromString('EUR/DKK');
        $date = new \DateTime('-2 days');
        $content = $this->buildFiveDayHistoryContent($date->format('Y-m-d'));

        $service = new DanishCentralBank($this->getHttpAdapterMock(DanishCentralBank::FIVE_DAY_HISTORY_URL, $content));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEqualsWithDelta(7.4746, $rate->getValue(), 1e-9);
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('danish_central_bank', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_throws_an_exception_when_recent_historical_date_is_missing(): void
    {
        $this->expectException(UnsupportedDateException::class);

        $date = new \DateTime('-1 days');
        $content = $this->buildFiveDayHistoryContent((new \DateTime('-2 days'))->format('Y-m-d'));

        $service = new DanishCentralBank($this->getHttpAdapterMock(DanishCentralBank::FIVE_DAY_HISTORY_URL, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/DKK'), $date));
    }

    #[Test]
    public function it_throws_an_exception_when_the_pair_is_not_supported_for_a_recent_historical_date(): void
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $date = new \DateTime('-2 days');
        $content = $this->buildFiveDayHistoryContent($date->format('Y-m-d'));

        $service = new DanishCentralBank($this->getHttpAdapterMock(DanishCentralBank::FIVE_DAY_HISTORY_URL, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('XXX/DKK'), $date));
    }

    #[Test]
    public function it_fetches_an_old_historical_rate(): void
    {
        $pair = CurrencyPair::createFromString('EUR/DKK');
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/DanishCentralBank/statbank-eur.json');

        $service = new DanishCentralBank($this->getHttpAdapterMock(DanishCentralBank::STATBANK_URL, $content));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('2024-03-14')));

        $this->assertEqualsWithDelta(7.4568, $rate->getValue(), 1e-9);
        $this->assertEquals(new \DateTime('2024-03-14'), $rate->getDate());
        $this->assertEquals('danish_central_bank', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_an_old_historical_rate_when_dkk_is_base(): void
    {
        $pair = CurrencyPair::createFromString('DKK/EUR');
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/DanishCentralBank/statbank-eur.json');

        $service = new DanishCentralBank($this->getHttpAdapterMock(DanishCentralBank::STATBANK_URL, $content));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTime('2024-03-14')));

        $this->assertSame(0.134106, $rate->getValue());
        $this->assertEquals(new \DateTime('2024-03-14'), $rate->getDate());
        $this->assertEquals('danish_central_bank', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_throws_an_exception_when_old_historical_date_is_missing(): void
    {
        $this->expectException(UnsupportedDateException::class);
        $this->expectExceptionMessage("The date \"2024-03-16\" is not supported by the service \"Exchanger\Service\DanishCentralBank\".");

        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/DanishCentralBank/statbank-notfound-date.json');

        $service = new DanishCentralBank($this->getHttpAdapterMock(DanishCentralBank::STATBANK_URL, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/DKK'), new \DateTime('2024-03-16')));
    }

    #[Test]
    public function it_has_a_name(): void
    {
        $service = new DanishCentralBank($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('danish_central_bank', $service->getName());
    }

    public static function getSupportedCurrencies(): array
    {
        return [
            ['AUD'],
            ['BRL'],
            ['CAD'],
            ['CHF'],
            ['CNY'],
            ['CZK'],
            ['EUR'],
            ['GBP'],
            ['HKD'],
            ['HUF'],
            ['IDR'],
            ['ILS'],
            ['INR'],
            ['ISK'],
            ['JPY'],
            ['KRW'],
            ['MXN'],
            ['MYR'],
            ['NOK'],
            ['NZD'],
            ['PHP'],
            ['PLN'],
            ['RON'],
            ['SEK'],
            ['SGD'],
            ['THB'],
            ['TRY'],
            ['USD'],
            ['XDR'],
            ['ZAR'],
        ];
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
