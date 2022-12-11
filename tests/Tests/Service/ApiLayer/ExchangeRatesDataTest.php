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
use Exchanger\Exception\Exception;
use Exchanger\Exception\NonBreakingInvalidArgumentException;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\ApiLayer\CurrencyData;
use Exchanger\Service\ApiLayer\ExchangeRatesData;

/**
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class ExchangeRatesDataTest extends ServiceTestCase
{
    /**
     * @test
     */
    public function it_throws_an_exception_if_api_key_option_missing()
    {
        $this->expectException(NonBreakingInvalidArgumentException::class);
        $this->expectExceptionMessage('The "api_key" option must be provided to use Exchange Rates Data (https://apilayer.com/marketplace/exchangerates_data-api).');
        new ExchangeRatesData($this->createMock('Http\Client\HttpClient'));
    }

    /**
     * @test
     */
    public function it_does_support_all_queries()
    {
        $service = new ExchangeRatesData(
            $this->createMock('Http\Client\HttpClient'),
            null,
            ['api_key' => 'x']
        );
        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
    }

    /**
     * @test
     * @dataProvider unsupportedCurrencyPairResponsesProvider
     */
    public function it_throws_An_unsupported_currency_pair_exception(
        string $contentPath,
        string $uri,
        string $accessKey,
        string $currencyPair,
        bool $historical = false,
        string $dateStr = '2020-04-15'
    ) {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $content = file_get_contents($contentPath);

        $service = new ExchangeRatesData(
            $this->getHttpAdapterMock($uri, $content),
            null,
            ['api_key' => $accessKey]
        );

        if ($historical) {
            $date = new \DateTimeImmutable($dateStr);
            $query = new HistoricalExchangeRateQuery(CurrencyPair::createFromString($currencyPair), $date);
        } else {
            $query = new ExchangeRateQuery(CurrencyPair::createFromString($currencyPair));
        }

        $service->getExchangeRate($query);
    }

    public function unsupportedCurrencyPairResponsesProvider(): array
    {
        $dir = __DIR__.'/../../../Fixtures/Service/ExchangeRatesData/';

        return [
            'invalid_base_currency' => [
                $dir.'invalid_base_currency.json',
                sprintf(ExchangeRatesData::LATEST_URL, $baseCurrency = 'XTS', $accessKey = 'valid', $currency = 'USD'),
                $accessKey,
                $baseCurrency.'/'.$currency,
            ],
            'invalid_currency_codes' => [
                $dir.'invalid_currency_codes.json',
                sprintf(ExchangeRatesData::LATEST_URL, $baseCurrency = 'USD', $accessKey = 'valid', $currency = 'XTS'),
                $accessKey,
                $baseCurrency.'/'.$currency,
            ],
            'no_rates_available' => [
                $dir.'no_rates_available.json',
                sprintf(ExchangeRatesData::HISTORICAL_URL, $date = '1998-12-31', $baseCurrency = 'USD', $accessKey = 'valid', $currency = 'EUR'),
                $accessKey,
                $baseCurrency.'/'.$currency,
                true,
                $date,
            ],
        ];
    }

    /**
     * @dataProvider errorResponsesProvider
     */
    public function it_throws_an_exception_with_error_response(
        string $contentPath,
        string $uri,
        string $accessKey,
        string $currencyPair,
        string $message,
        bool $historical = false,
        string $dateStr = '2020-04-15'
    ) {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        $content = file_get_contents($contentPath);

        $service = new ExchangeRatesData(
            $this->getHttpAdapterMock($uri, $content),
            null,
            ['api_key' => $accessKey]
        );
        if ($historical) {
            $date = new \DateTimeImmutable($dateStr);
            $query = new HistoricalExchangeRateQuery(CurrencyPair::createFromString($currencyPair), $date);
        } else {
            $query = new ExchangeRateQuery(CurrencyPair::createFromString($currencyPair));
        }
        $service->getExchangeRate($query);
    }

    public function errorResponsesProvider(): array
    {
        $dir = __DIR__.'/../../Fixtures/Service/ExchangeRatesData/';

        return [
            'invalid_api_key' => [
                $dir.'invalid_api_key.json',
                sprintf(ExchangeRatesData::LATEST_URL, $accessKey = 'invalid', $currency = 'USD'),
                $accessKey,
                'EUR/'.$currency,
                'You have not supplied a valid API Access Key. [Technical Support: support@apilayer.com]',
            ],
            'base_currency_access_restricted' => [
                $dir.'base_currency_access_restricted.json',
                sprintf(ExchangeRatesData::LATEST_URL, $baseCurrency = 'USD', $accessKey = 'valid', $currency = 'EUR'),
                $accessKey,
                $baseCurrency.'/'.$currency,
                'An unexpected error ocurred. [Technical Support: support@apilayer.com]',
            ],
            'https_access_restricted' => [
                $dir.'https_access_restricted.json',
                sprintf(ExchangeRatesData::LATEST_URL, $baseCurrency = 'EUR', $accessKey = 'valid', $currency = 'USD'),
                $accessKey,
                $baseCurrency.'/'.$currency,
                'Access Restricted - Your current Subscription Plan does not support HTTPS Encryption.',
            ],
            'invalid_date' => [
                $dir.'invalid_date.json',
                sprintf(ExchangeRatesData::HISTORICAL_URL, $date = '2056-01-01', $accessKey = 'valid', $currency = 'USD'),
                $accessKey,
                $baseCurrency.'/'.$currency,
                'You have entered an invalid date. [Required format: date=YYYY-MM-DD]',
                true,
                $date,
            ],
        ];
    }

    /**
     * @test
     */
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/USD');
        $uri = 'https://api.apilayer.com/exchangerates_data/latest?base=EUR&api_key=x&symbols=USD';
        $content = file_get_contents(__DIR__.'/../../Fixtures/Service/ApiLayer/ExchangeRatesData/latest.json');

        $service = new ExchangeRatesData(
            $this->getHttpAdapterMock($uri, $content),
            null,
            ['api_key' => 'x']
        );
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertEquals(1.20555, $rate->getValue());
        $this->assertEquals(new \DateTime('2021-04-23'), $rate->getDate());
        $this->assertEquals('apilayer_exchange_rates_data', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/USD');
        $uri = 'https://api.apilayer.com/exchangerates_data/2021-04-15?base=EUR&apikey=x&symbols=USD';
        $content = file_get_contents(__DIR__.'/../../../Fixtures/Service/ApiLayer/ExchangeRatesData/historical.json');
        $date = new \DateTime('2021-04-15');

        $service = new ExchangeRatesData(
            $this->getHttpAdapterMock($uri, $content),
            null,
            ['api_key' => 'x']
        );
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEquals(1.196953, $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('apilayer_exchange_rates_data', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new ExchangeRatesData($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'x']);

        $this->assertSame('apilayer_exchange_rates_data', $service->getName());
    }
}
