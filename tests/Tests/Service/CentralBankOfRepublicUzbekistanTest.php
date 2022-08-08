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
use Exchanger\Service\CentralBankOfRepublicUzbekistan;

class CentralBankOfRepublicUzbekistanTest extends ServiceTestCase
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
        self::$url = 'https://cbu.uz/common/json';
        self::$historicalUrl = 'https://cbu.uz/common/json?date=15.07.2020';
        self::$content = file_get_contents(__DIR__ . '/../../Fixtures/Service/CentralBankOfRepublicUzbekistan/cbru_today.json');
        self::$historicalContent = file_get_contents(__DIR__ . '/../../Fixtures/Service/CentralBankOfRepublicUzbekistan/cbru_historical.json');
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
        return new CentralBankOfRepublicUzbekistan($this->getHttpAdapterMock(self::$url, self::$content));
    }

    /**
     * Create bank service for historical rates.
     */
    protected function createServiceForHistoricalRates()
    {
        return new CentralBankOfRepublicUzbekistan($this->getHttpAdapterMock(self::$historicalUrl, self::$historicalContent));
    }

    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new CentralBankOfRepublicUzbekistan($this->createMock('Http\Client\HttpClient'));

        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('UZS/EUR'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/GBP'))));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $service = new CentralBankOfRepublicUzbekistan($this->getHttpAdapterMock(self::$url, self::$content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/UZS')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/UZS');
        $service = $this->createService();
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(11125.18, $rate->getValue());
        $this->assertEquals(new \DateTime('2022-08-05'), $rate->getDate());
        $this->assertEquals('central_bank_of_republic_uzbekistan', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/UZS');
        $requestedDate = new \DateTime('2020-07-15');
        $service = $this->createServiceForHistoricalRates();
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $requestedDate));

        $this->assertEquals(11518.27, $rate->getValue());
        $this->assertEquals(new \DateTime('2020-07-14'), $rate->getDate());
        $this->assertEquals('central_bank_of_republic_uzbekistan', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_normalizes_a_rate()
    {
        $pair = CurrencyPair::createFromString('IRR/UZS');
        $service = $this->createService();
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.260, $rate->getValue());
        $this->assertEquals(new \DateTime('2022-08-05'), $rate->getDate());
        $this->assertEquals('central_bank_of_republic_uzbekistan', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new CentralBankOfRepublicUzbekistan($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('central_bank_of_republic_uzbekistan', $service->getName());
    }
}
