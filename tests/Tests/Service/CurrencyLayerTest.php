<?php

/*
 * This file is part of Exchanger.
 *
 * (c) Pascal Hofmann <mail@pascalhofmann.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Tests\Service;

use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\CurrencyLayer;

class CurrencyLayerTest extends ServiceTestCase
{
    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The "access_key" option must be provided.
     */
    public function it_throws_an_exception_if_access_key_option_missing()
    {
        new CurrencyLayer($this->getMock('Http\Client\HttpClient'));
    }

    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        $service = new CurrencyLayer($this->getMock('Http\Client\HttpClient'), null, ['access_key' => 'secret']);
        $this->assertFalse($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/EUR'))));
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\Exception
     */
    public function it_throws_an_exception_with_error_response()
    {
        $uri = 'http://www.apilayer.net/api/live?access_key=secret&currencies=EUR';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyLayer/error.json');

        $service = new CurrencyLayer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'secret']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_normal_mode()
    {
        $uri = 'http://www.apilayer.net/api/live?access_key=secret&currencies=EUR';
        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp(1399748450);
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyLayer/success.json');

        $service = new CurrencyLayer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'secret']);
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));

        $this->assertEquals('0.726804', $rate->getValue());
        $this->assertEquals($expectedDate, $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_rate_enterprise_mode()
    {
        $uri = 'https://www.apilayer.net/api/live?access_key=secret&source=USD&currencies=EUR';
        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp(1399748450);
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyLayer/success.json');

        $service = new CurrencyLayer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'secret', 'enterprise' => true]);
        $rate = $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));

        $this->assertEquals('0.726804', $rate->getValue());
        $this->assertEquals($expectedDate, $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate_normal_mode()
    {
        $uri = 'http://apilayer.net/api/historical?access_key=secret&date=2015-05-06';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyLayer/historical_success.json');
        $date = new \DateTime('2015-05-06');
        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp(1430870399);

        $service = new CurrencyLayer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'secret']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('USD/AED'), $date));

        $this->assertEquals('3.673069', $rate->getValue());
        $this->assertEquals($expectedDate, $rate->getDate());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate_enterprise_mode()
    {
        $uri = 'https://apilayer.net/api/historical?access_key=secret&date=2015-05-06&source=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/CurrencyLayer/historical_success.json');
        $date = new \DateTime('2015-05-06');
        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp(1430870399);

        $service = new CurrencyLayer($this->getHttpAdapterMock($uri, $content), null, ['access_key' => 'secret', 'enterprise' => true]);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('USD/AED'), $date));

        $this->assertEquals('3.673069', $rate->getValue());
        $this->assertEquals($expectedDate, $rate->getDate());
    }
}
