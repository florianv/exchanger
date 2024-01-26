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
use Exchanger\Service\NationalBankOfRomania;
use Http\Client\HttpClient;

class NationalBankOfRomaniaTest extends ServiceTestCase
{
    /**
     * @test
     * @dataProvider getSupportedCurrencies
     *
     * @param string $currency
     */
    public function it_does_not_support_all_queries(string $currency): void
    {
        $service = new NationalBankOfRomania($this->createMock(HttpClient::class));

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString($currency.'/RON'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('RON/'.$currency))));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString($currency.'/RON'), new \DateTime())));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('RON/'.$currency), new \DateTime())));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/'.$currency))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString($currency.'/EUR'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/'.$currency), new \DateTime())));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString($currency.'/EUR'), new \DateTime())));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported(): void
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $url = 'https://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/RON')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate(): void
    {
        $pair = CurrencyPair::createFromString('EUR/RON');
        $url = 'https://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(4.5125, $rate->getValue());
        $this->assertEquals(new \DateTime('2016-12-02'), $rate->getDate());
        $this->assertEquals('national_bank_of_romania', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_multiplier_rate(): void
    {
        $pair = CurrencyPair::createFromString('HUF/RON');
        $url = 'https://www.bnr.ro/nbrfxrates.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.014356, $rate->getValue());
        $this->assertEquals(new \DateTime('2016-12-02'), $rate->getDate());
        $this->assertEquals('national_bank_of_romania', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate(): void
    {
        $pair = CurrencyPair::createFromString('EUR/RON');
        $url = 'https://www.bnr.ro/files/xml/years/nbrfxrates2018.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates2018.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(
            new HistoricalExchangeRateQuery($pair, new \DateTime('2018-02-02'))
        );

        $this->assertSame(4.6526, $rate->getValue());
        $this->assertEquals(new \DateTime('2018-02-02'), $rate->getDate());
        $this->assertEquals('national_bank_of_romania', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_historical_date_is_not_supported(): void
    {
        $this->expectException(UnsupportedDateException::class);
        $this->expectExceptionMessage("The date \"2018-02-25\" is not supported by the service \"Exchanger\Service\NationalBankOfRomania\".");

        $url = 'https://www.bnr.ro/files/xml/years/nbrfxrates2018.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates2018.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/RON'), new \DateTime('2018-02-25'))
        );
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported_historical(): void
    {
        $this->expectExceptionMessage("The currency pair \"RON/XXL\" is not supported by the service \"Exchanger\Service\NationalBankOfRomania\".");
        $this->expectException(UnsupportedCurrencyPairException::class);
        $url = 'https://www.bnr.ro/files/xml/years/nbrfxrates2018.xml';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRomania/nbrfxrates2018.xml');

        $service = new NationalBankOfRomania($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(
            new HistoricalExchangeRateQuery(CurrencyPair::createFromString('RON/XXL'), new \DateTime('2018-02-02'))
        );
    }

    /**
     * @test
     */
    public function it_has_a_name(): void
    {
        $service = new NationalBankOfRomania($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('national_bank_of_romania', $service->getName());
    }

    public static function getSupportedCurrencies(): array
    {
        return [
            ['AED'],
            ['AUD'],
            ['BGN'],
            ['BRL'],
            ['CAD'],
            ['CHF'],
            ['CNY'],
            ['CZK'],
            ['DKK'],
            ['EGP'],
            ['EUR'],
            ['GBP'],
            ['HRK'],
            ['HUF'],
            ['INR'],
            ['JPY'],
            ['KRW'],
            ['MDL'],
            ['MXN'],
            ['NOK'],
            ['NZD'],
            ['PLN'],
            ['RSD'],
            ['RUB'],
            ['SEK'],
            ['TRY'],
            ['UAH'],
            ['USD'],
            ['XAU'],
            ['XDR'],
            ['ZAR'],
        ];
    }
}
