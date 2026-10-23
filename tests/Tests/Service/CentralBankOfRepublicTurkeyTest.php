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

use Exchanger\Exception\Exception;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\CentralBankOfRepublicTurkey;
use PHPUnit\Framework\Attributes\Test;
use Http\Client\HttpClient;
use Exchanger\Tests\FileContents;

class CentralBankOfRepublicTurkeyTest extends ServiceTestCase
{
    /**
     * @var string URL of CBRT exchange rates
     */
    protected static $url;

    /**
     * @var string content of CBRT exchange rates
     */
    protected static $content;

    /**
     * @var string URL of CBRT historical exchange rates
     */
    protected static $historicalUrl;

    /**
     * @var string content of CBRT historical exchange rates
     */
    protected static $historicalContent;

    /**
     * Set up variables before TestCase is being initialized.
     */
    #[\Override]
    public static function setUpBeforeClass(): void
    {
        self::$url = 'https://www.tcmb.gov.tr/kurlar/today.xml';
        self::$historicalUrl = 'https://www.tcmb.gov.tr/kurlar/201304/23042013.xml';
        self::$content = FileContents::read(__DIR__ . '/../../Fixtures/Service/CentralBankOfRepublicTurkey/cbrt_today.xml');
        self::$historicalContent = FileContents::read(__DIR__ . '/../../Fixtures/Service/CentralBankOfRepublicTurkey/cbrt_historical.xml');
    }

    /**
     * Clean variables after TestCase finish.
     */
    #[\Override]
    public static function tearDownAfterClass(): void
    {
        self::$url = null;
        self::$content = null;
    }

    /**
     * Create bank service for historical rates.
     *
     * @return CentralBankOfRepublicTurkey
     */
    protected function createServiceForHistoricalRates()
    {
        return new CentralBankOfRepublicTurkey($this->getHttpAdapterMock(self::$historicalUrl, self::$historicalContent));
    }

    #[Test]
    public function it_throws_an_exception_when_the_rate_date_is_missing()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The response of the Central Bank of the Republic of Turkey has no rate date.');

        $content = str_replace(' Date="03/15/2016"', '', self::$content);

        $service = new CentralBankOfRepublicTurkey($this->getHttpAdapterMock(self::$url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/TRY')));
    }

    #[Test]
    public function it_does_not_support_all_queries()
    {
        $service = new CentralBankOfRepublicTurkey($this->createMock(HttpClient::class));

        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('TRY/EUR'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/GBP'))));
    }

    #[Test]
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);
        $url = 'https://www.tcmb.gov.tr/kurlar/today.xml';
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/CentralBankOfRepublicTurkey/cbrt_today.xml');

        $service = new CentralBankOfRepublicTurkey($this->getHttpAdapterMock($url, $content));
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/TRY')));
    }

    #[Test]
    public function it_fetches_a_rate()
    {
        $url = 'https://www.tcmb.gov.tr/kurlar/today.xml';
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/CentralBankOfRepublicTurkey/cbrt_today.xml');

        $pair = CurrencyPair::createFromString('EUR/TRY');
        $service = new CentralBankOfRepublicTurkey($this->getHttpAdapterMock($url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(3.2083, $rate->getValue());
        $this->assertEquals(new \DateTime('2016-03-15'), $rate->getDate());
        $this->assertEquals('central_bank_of_republic_turkey', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/TRY');
        $requestedDate = new \DateTime('2013-04-23');
        $service = $this->createServiceForHistoricalRates();
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $requestedDate));

        $this->assertEquals(2.3544, $rate->getValue());
        $this->assertEquals(new \DateTime('2013-04-22'), $rate->getDate());
        $this->assertEquals('central_bank_of_republic_turkey', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_has_a_name()
    {
        $service = new CentralBankOfRepublicTurkey($this->createMock(HttpClient::class));

        $this->assertSame('central_bank_of_republic_turkey', $service->getName());
    }
}
