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
use Exchanger\Service\NationalBankOfRepublicBelarus;

class NationalBankOfRepublicBelarusTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new NationalBankOfRepublicBelarus($this->createMock('Http\Client\HttpClient'));

        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('BYN/EUR'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/GBP'))));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $service = new NationalBankOfRepublicBelarus($this->createMock('Http\Client\HttpClient'));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/BYN')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $url = 'https://www.nbrb.by/api/exrates/rates?periodicity=0';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRepublicBelarus/nbrb_today.json');
        $today = new \DateTimeImmutable('today');
        $content = sprintf($content, $today->format('Y-m-d\TH:i:s'));

        $currencyPair = CurrencyPair::createFromString('EUR/BYN');
        $service = new NationalBankOfRepublicBelarus($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($currencyPair));

        $this->assertSame(2.5423, $rate->getValue());
        $this->assertEquals($today, $rate->getDate());
        $this->assertEquals('national_bank_of_republic_belarus', $rate->getProviderName());
        $this->assertSame($currencyPair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $url = 'https://www.nbrb.by/api/exrates/rates?ondate=2020-03-07&periodicity=0';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/NationalBankOfRepublicBelarus/nbrb_historical.json');
        $currencyPair = CurrencyPair::createFromString('EUR/BYN');
        $requestedDate = new \DateTimeImmutable('2020-03-07');

        $service = new NationalBankOfRepublicBelarus($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($currencyPair, $requestedDate));

        $this->assertEquals(2.5178, $rate->getValue());
        $this->assertEquals(new \DateTimeImmutable('2020-03-07'), $rate->getDate());
        $this->assertEquals('national_bank_of_republic_belarus', $rate->getProviderName());
        $this->assertSame($currencyPair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_historical_date_is_not_supported()
    {
        $this->expectException(UnsupportedDateException::class);
        $this->expectExceptionMessage("The date \"1995-01-01\" is not supported by the service \"Exchanger\Service\NationalBankOfRepublicBelarus\".");

        $currencyPair = CurrencyPair::createFromString('EUR/BYN');
        $requestedDate = new \DateTimeImmutable('1995-01-01');

        $service = new NationalBankOfRepublicBelarus($this->createMock('Http\Client\HttpClient'));
        $service->getExchangeRate(new HistoricalExchangeRateQuery($currencyPair, $requestedDate));
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new NationalBankOfRepublicBelarus($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('national_bank_of_republic_belarus', $service->getName());
    }
}
