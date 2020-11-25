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
use Exchanger\Service\NationalBankOfUkraine;

/**
 * Tests for National Bank of Ukraine.
 *
 * @author Ilya Zelenin <ilya.zelenin@make.im>
 */
class NationalBankOfUkraineTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new NationalBankOfUkraine($this->createMock('Http\Client\HttpClient'));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/UAH'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/UAH'), new \DateTime())));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);
        $expectedExceptionMessage = 'The currency pair "XXL/UAH" is not supported by the service "Exchanger\Service\NationalBankOfUkraine".';
        $this->expectExceptionMessage($expectedExceptionMessage);

        $url = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfUkraine/success.xml');

        $service = new NationalBankOfUkraine($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXL/UAH')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/UAH');
        $url = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfUkraine/success.xml');

        $service = new NationalBankOfUkraine($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(30.681129, $rate->getValue());
        $this->assertEquals(new \DateTime('2019-02-18'), $rate->getDate());
        $this->assertEquals('national_bank_of_ukraine', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_nominational_rate()
    {
        $pair = CurrencyPair::createFromString('AMD/UAH');
        $url = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfUkraine/success.xml');

        $service = new NationalBankOfUkraine($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.0569653, $rate->getValue());
        $this->assertEquals(new \DateTime('2019-02-18'), $rate->getDate());
        $this->assertEquals('national_bank_of_ukraine', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('USD/UAH');
        $url = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?date=20190101';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfUkraine/historical.xml');

        $service = new NationalBankOfUkraine($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(
            new HistoricalExchangeRateQuery($pair, new \DateTime('2019-01-01'))
        );

        $this->assertSame(27.688264, $rate->getValue());
        $this->assertEquals(new \DateTime('2019-01-01'), $rate->getDate());
        $this->assertEquals('national_bank_of_ukraine', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_historical_date_is_not_supported()
    {
        $this->expectException(UnsupportedDateException::class);
        $expectedExceptionMessage = 'The date "1990-01-01" is not supported by the service "Exchanger\Service\NationalBankOfUkraine".';
        $this->expectExceptionMessage($expectedExceptionMessage);

        $url = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?date=19900101';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfUkraine/historical_error.xml');

        $service = new NationalBankOfUkraine($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/UAH'), new \DateTime('1990-01-01')));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported_historical()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);
        $expectedExceptionMessage = 'The currency pair "XXL/UAH" is not supported by the service "Exchanger\Service\NationalBankOfUkraine".';
        $this->expectExceptionMessage($expectedExceptionMessage);

        $url = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?date=20190101';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfUkraine/historical.xml');

        $service = new NationalBankOfUkraine($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('XXL/UAH'), new \DateTime('2019-01-01')));
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new NationalBankOfUkraine($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('national_bank_of_ukraine', $service->getName());
    }
}
