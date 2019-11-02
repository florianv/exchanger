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
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\BulgarianNationalBank;

/**
 * @author Marin Bezhanov
 */
class BulgarianNationalBankTest extends ServiceTestCase
{
    protected static $url;

    protected static $historicalUrl;

    public static function setUpBeforeClass()
    {
        self::$url = sprintf(BulgarianNationalBank::URL, date('d'), date('m'), date('Y'));
        self::$historicalUrl = sprintf(BulgarianNationalBank::URL, '01', '02', '2019');
    }

    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new BulgarianNationalBank($this->createMock('Http\Client\HttpClient'));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/BGN'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/BGN'), new \DateTime())));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('USD/BGN');
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/BulgarianNationalBank/success.xml');

        $service = new BulgarianNationalBank($this->getHttpAdapterMock(self::$url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(1.70502, $rate->getValue());
        $this->assertEquals(new \DateTime('2019-02-01'), $rate->getDate());
        $this->assertEquals('bulgarian_national_bank', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_historical()
    {
        $pair = CurrencyPair::createFromString('USD/BGN');
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/BulgarianNationalBank/success.xml');

        $service = new BulgarianNationalBank($this->getHttpAdapterMock(self::$historicalUrl, $content));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTimeImmutable('2019-02-01')));

        $this->assertSame(1.70502, $rate->getValue());
        $this->assertEquals(new \DateTime('2019-02-01'), $rate->getDate());
        $this->assertEquals('bulgarian_national_bank', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_with_ratio()
    {
        $pair = CurrencyPair::createFromString('IDR/BGN');
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/BulgarianNationalBank/success.xml');

        $service = new BulgarianNationalBank($this->getHttpAdapterMock(self::$url, $content));
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.000122167, $rate->getValue());
        $this->assertEquals(new \DateTime('2019-02-01'), $rate->getDate());
        $this->assertEquals('bulgarian_national_bank', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_with_ratio_historical()
    {
        $pair = CurrencyPair::createFromString('IDR/BGN');
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/BulgarianNationalBank/success.xml');

        $service = new BulgarianNationalBank($this->getHttpAdapterMock(self::$historicalUrl, $content));
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTimeImmutable('2019-02-01')));

        $this->assertSame(0.000122167, $rate->getValue());
        $this->assertEquals(new \DateTime('2019-02-01'), $rate->getDate());
        $this->assertEquals('bulgarian_national_bank', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedDateException
     */
    public function it_throws_an_exception_when_it_cannot_retrieve_an_xml_document_for_the_requested_date()
    {
        $expectedExceptionMessage = 'The date "%s" is not supported by the service "Exchanger\Service\BulgarianNationalBank".';
        $this->expectExceptionMessage(sprintf($expectedExceptionMessage, date('Y-m-d')));

        $pair = CurrencyPair::createFromString('EUR/BGN');
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/BulgarianNationalBank/failure.html');

        $service = new BulgarianNationalBank($this->getHttpAdapterMock(self::$url, $content));
        $service->getExchangeRate(new ExchangeRateQuery($pair));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedDateException
     * @expectedExceptionMessage The date "2019-02-01" is not supported by the service "Exchanger\Service\BulgarianNationalBank".
     */
    public function it_throws_an_exception_when_it_cannot_retrieve_an_xml_document_for_the_requested_date_historical()
    {
        $pair = CurrencyPair::createFromString('EUR/BGN');
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/BulgarianNationalBank/failure.html');

        $service = new BulgarianNationalBank($this->getHttpAdapterMock(self::$historicalUrl, $content));
        $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, new \DateTimeImmutable('2019-02-01')));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     * @expectedExceptionMessage The currency pair "ABC/BGN" is not supported by the service "Exchanger\Service\BulgarianNationalBank".
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $pair = CurrencyPair::createFromString('ABC/BGN');
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/BulgarianNationalBank/success.xml');

        $service = new BulgarianNationalBank($this->getHttpAdapterMock(self::$url, $content));
        $service->getExchangeRate(new ExchangeRateQuery($pair));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\UnsupportedCurrencyPairException
     * @expectedExceptionMessage The currency pair "ABC/BGN" is not supported by the service "Exchanger\Service\BulgarianNationalBank".
     */
    public function it_throws_an_exception_when_the_pair_is_not_supported_historical()
    {
        $pair = CurrencyPair::createFromString('ABC/BGN');
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/BulgarianNationalBank/success.xml');

        $service = new BulgarianNationalBank($this->getHttpAdapterMock(self::$historicalUrl, $content));
        $service->getExchangeRate(
            new HistoricalExchangeRateQuery($pair, new \DateTimeImmutable('2019-02-01'))
        );
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new BulgarianNationalBank($this->createMock('Http\Client\HttpClient'));

        $this->assertSame('bulgarian_national_bank', $service->getName());
    }
}
