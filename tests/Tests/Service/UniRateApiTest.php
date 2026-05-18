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
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\Service\UniRateApi;
use PHPUnit\Framework\Attributes\Test;

class UniRateApiTest extends ServiceTestCase
{
    #[Test]
    public function it_throws_an_exception_when_api_key_option_missing()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('api_key');

        new UniRateApi($this->createMock('Http\Client\HttpClient'));
    }

    #[Test]
    public function it_supports_all_queries()
    {
        $service = new UniRateApi($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    #[Test]
    public function it_fetches_a_latest_rate()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $url = 'https://api.unirateapi.com/api/rates?api_key=secret&from=USD&to=EUR';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/UniRateApi/rate-usd-eur.json');
        $service = new UniRateApi($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.925, $rate->getValue());
        $this->assertSame('unirate_api', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $date = new \DateTime('2024-01-15');
        $url = 'https://api.unirateapi.com/api/historical/rates?api_key=secret&date=2024-01-15&from=USD&to=EUR';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/UniRateApi/historical-usd-eur.json');
        $service = new UniRateApi($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertSame(0.91, $rate->getValue());
        $this->assertSame('2024-01-15', $rate->getDate()->format('Y-m-d'));
        $this->assertSame('unirate_api', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_throws_an_exception_when_response_has_error_field()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Currency not found or no data available');

        $url = 'https://api.unirateapi.com/api/rates?api_key=secret&from=USD&to=ZZZ';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/UniRateApi/error-invalid-currency.json');
        $service = new UniRateApi($this->getHttpAdapterMock($url, $content, 404), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/ZZZ')));
    }

    #[Test]
    public function it_throws_an_exception_when_response_status_error()
    {
        $this->expectException(Exception::class);

        $url = 'https://api.unirateapi.com/api/rates?api_key=secret&from=USD&to=EUR';
        $service = new UniRateApi($this->getHttpAdapterMock($url, '', 401), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    #[Test]
    public function it_throws_an_exception_when_rate_field_missing()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $url = 'https://api.unirateapi.com/api/rates?api_key=secret&from=USD&to=EUR';
        $service = new UniRateApi($this->getHttpAdapterMock($url, '{}'), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    #[Test]
    public function it_has_a_name()
    {
        $service = new UniRateApi($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertSame('unirate_api', $service->getName());
    }
}
