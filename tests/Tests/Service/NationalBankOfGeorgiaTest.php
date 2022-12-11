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
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\NationalBankOfGeorgia;

class NationalBankOfGeorgiaTest extends ServiceTestCase
{
    /** @var string $url */
    protected static $url;

    /** @var string $content */
    protected static $content;

    /** @var string $historicalUrl */
    protected static $historicalUrl;

    /** @var string $historicalContent */
    protected static $historicalContent;

    /**
     * Set up variables before TestCase is being initialized.
     */
    public static function setUpBeforeClass(): void
    {
        self::$url = 'https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/en/json';
        self::$historicalUrl = 'https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/en/json?date=2020-07-15';
        self::$content = file_get_contents(__DIR__ . '/../../Fixtures/Service/NationalBankOfGeorgia/nbog_today.json');
        self::$historicalContent = file_get_contents(__DIR__ . '/../../Fixtures/Service/NationalBankOfGeorgia/nbog_historical.json');
    }

    /**
     * Clean variables after TestCase finish.
     */
    public static function tearDownAfterClass(): void
    {
        self::$url = null;
        self::$content = null;
        self::$historicalUrl = null;
        self::$historicalContent = null;
    }

    /**
     * Create bank service.
     */
    protected function createService()
    {
        return new NationalBankOfGeorgia($this->getHttpAdapterMock(self::$url, self::$content));
    }

    /**
     * Create bank service for historical rates.
     */
    protected function createServiceForHistoricalRates()
    {
        return new NationalBankOfGeorgia($this->getHttpAdapterMock(self::$historicalUrl, self::$historicalContent));
    }

    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new NationalBankOfGeorgia($this->createMock('Http\Client\HttpClient'));

        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('GEL/EUR'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/GBP'))));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $service = new NationalBankOfGeorgia($this->getHttpAdapterMock(self::$url, self::$content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/GEL')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/GEL');
        $service = $this->createService();
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(2.7806, $rate->getValue());
        $this->assertEquals(new \DateTime('2022-08-06'), $rate->getDate());
        $this->assertEquals('national_bank_of_georgia', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/GEL');
        $requestedDate = new \DateTime('2020-07-15');
        $service = $this->createServiceForHistoricalRates();
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $requestedDate));

        $this->assertEquals(3.4817, $rate->getValue());
        $this->assertEquals(new \DateTime('2020-07-15'), $rate->getDate());
        $this->assertEquals('national_bank_of_georgia', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_normalizes_a_rate()
    {
        $pair = CurrencyPair::createFromString('JPY/GEL');
        $service = $this->createService();
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.020413, $rate->getValue());
        $this->assertEquals(new \DateTime('2022-08-06'), $rate->getDate());
        $this->assertEquals('national_bank_of_georgia', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new NationalBankOfGeorgia($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('national_bank_of_georgia', $service->getName());
    }
}
