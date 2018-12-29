<?php

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
use Exchanger\Service\CentralBankOfCzechRepublic;

/**
 * @author Petr Kramar <petr.kramar@perlur.cz>
 */
class CentralBankOfCzechRepublicTest extends ServiceTestCase
{
    /**
     * @var string URL of CNB exchange rates
     */
    protected static $url;

    /**
     * @var string content of CNB exchange rates
     */
    protected static $content;

    /**
     * @var string URL of CNB historical exchange rates
     */
    protected static $historicalUrl;

    /**
     * @var string content of CNB historical exchange rates
     */
    protected static $historicalContent;

    /**
     * Set up variables before TestCase is being initialized.
     */
    public static function setUpBeforeClass()
    {
        self::$url = 'http://www.cnb.cz/cs/financni_trhy/devizovy_trh/kurzy_devizoveho_trhu/denni_kurz.txt';
        self::$historicalUrl = 'http://www.cnb.cz/cs/financni_trhy/devizovy_trh/kurzy_devizoveho_trhu/denni_kurz.txt?date=23.04.2000';
        self::$content = file_get_contents(__DIR__.'/../../Fixtures/Service/CentralBankOfCzechRepublic/cnb_today.txt');
        self::$historicalContent = file_get_contents(__DIR__.'/../../Fixtures/Service/CentralBankOfCzechRepublic/cnb_historical.txt');
    }

    /**
     * Clean variables after TestCase finish.
     */
    public static function tearDownAfterClass()
    {
        self::$url = null;
        self::$content = null;
    }

    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new CentralBankOfCzechRepublic($this->createMock('Http\Client\HttpClient'));

        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('CZK/EUR'))));
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/TRY'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('XXX/TRY'), new \DateTime())));
    }

    /**
     * @test
     */
    public function itFetchesAEurRate()
    {
        $service = $this->createService();
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/CZK')));

        $this->assertSame('27.035', $rate->getValue());
        $this->assertEquals(new \DateTime('2016-04-05'), $rate->getDate());
    }

    /**
     * @test
     */
    public function itFetchesAPhpRate()
    {
        $rate = $this->createService()->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('PHP/CZK')));
        $this->assertSame('0.51384', $rate->getValue());
    }

    /**
     * @test
     */
    public function itFetchesAIdrRate()
    {
        $rate = $this->createService()->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('IDR/CZK')));
        $this->assertSame('0.001798', $rate->getValue());
    }

    /**
     * @test
     */
    public function itFetchesAHistoricalFrfRate()
    {
        $requestedDate = new \DateTime('2000-04-23');
        $service = $this->createServiceForHistoricalRates();
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('FRF/CZK'), $requestedDate));

        $this->assertEquals('5.529', $rate->getValue());
        $this->assertEquals(new \DateTime('2000-04-21'), $rate->getDate());
    }

    /**
     * @test
     */
    public function itFetchesAHistoricalEurRate()
    {
        $requestedDate = new \DateTime('2000-04-23');
        $service = $this->createServiceForHistoricalRates();
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/CZK'), $requestedDate));

        $this->assertEquals('36.27', $rate->getValue());
        $this->assertEquals(new \DateTime('2000-04-21'), $rate->getDate());
    }

    /**
     * Create bank service.
     *
     * @return CentralBankOfCzechRepublic
     */
    protected function createService()
    {
        return new CentralBankOfCzechRepublic($this->getHttpAdapterMock(self::$url, self::$content));
    }

    /**
     * Create bank service for historical rates.
     *
     * @return CentralBankOfCzechRepublic
     */
    protected function createServiceForHistoricalRates()
    {
        return new CentralBankOfCzechRepublic($this->getHttpAdapterMock(self::$historicalUrl, self::$historicalContent));
    }
}
