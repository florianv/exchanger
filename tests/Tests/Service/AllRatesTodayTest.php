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
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\AllRatesToday;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;

class AllRatesTodayTest extends ServiceTestCase
{
    #[Test]
    public function it_throws_an_exception_when_api_key_option_missing()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('api_key');

        new AllRatesToday($this->createMock('Http\Client\HttpClient'));
    }

    #[Test]
    public function it_does_not_support_historical_queries()
    {
        $service = new AllRatesToday($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
        $this->assertFalse($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'), new \DateTime())));
    }

    #[Test]
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/AllRatesToday/rate-usd-eur.json');
        $service = new AllRatesToday(
            $this->getHttpAdapterMock($url, $content, 200, function (RequestInterface $request): bool {
                return $request->getHeaderLine('Authorization') === 'Bearer secret';
            }),
            null,
            ['api_key' => 'secret'],
        );

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.85, $rate->getValue());
        $this->assertSame('2026-10-22', $rate->getDate()->format('Y-m-d'));
        $this->assertSame('allratestoday', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_throws_an_exception_when_response_has_error_field()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid API key');

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/AllRatesToday/error-invalid-key.json');
        $service = new AllRatesToday($this->getHttpAdapterMock($url, $content, 401), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    #[Test]
    public function it_throws_an_exception_when_api_key_option_is_blank()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('api_key');

        new AllRatesToday($this->createMock('Http\Client\HttpClient'), null, ['api_key' => " \n"]);
    }

    #[Test]
    public function it_trims_the_api_key()
    {
        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/AllRatesToday/rate-usd-eur.json');
        $service = new AllRatesToday(
            $this->getHttpAdapterMock($url, $content, 200, function (RequestInterface $request): bool {
                return $request->getHeaderLine('Authorization') === 'Bearer secret';
            }),
            null,
            ['api_key' => "secret\n"],
        );

        $this->assertSame(0.85, $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')))->getValue());
    }

    #[Test]
    public function it_matches_the_quote_currency_case_insensitively()
    {
        $url = 'https://allratestoday.com/api/v1/rates?source=usd&target=eur';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/AllRatesToday/rate-usd-eur.json');
        $service = new AllRatesToday($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new ExchangeRateQuery(new CurrencyPair('usd', 'eur')));

        $this->assertSame(0.85, $rate->getValue());
    }

    #[Test]
    public function it_throws_an_exception_when_response_status_error()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed with HTTP response code 503');

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';
        $service = new AllRatesToday($this->getHttpAdapterMock($url, '{"message":"x"}', 503), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    #[Test]
    public function it_keeps_the_status_code_when_the_error_body_is_not_json()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to parse response (HTTP response code 502)');

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';
        $service = new AllRatesToday($this->getHttpAdapterMock($url, '<html>Bad Gateway</html>', 502), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    #[Test]
    public function it_throws_an_exception_when_a_200_response_has_an_error_field()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Quota exceeded');

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';
        $service = new AllRatesToday($this->getHttpAdapterMock($url, '{"error":"Quota exceeded"}'), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    #[Test]
    public function it_throws_an_exception_when_a_200_response_is_empty()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to parse response (HTTP response code 200)');

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';
        $service = new AllRatesToday($this->getHttpAdapterMock($url, ''), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    #[Test]
    public function it_throws_an_unsupported_currency_pair_exception_on_http_400()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=XYZ';
        $service = new AllRatesToday($this->getHttpAdapterMock($url, '{"error":"Upstream API error: 400"}', 400), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/XYZ')));
    }

    #[Test]
    public function it_throws_an_exception_when_quote_currency_missing()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=ZZZ';
        $service = new AllRatesToday($this->getHttpAdapterMock($url, '[]'), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/ZZZ')));
    }

    #[Test]
    public function it_throws_an_unsupported_currency_pair_exception_when_rate_is_not_numeric()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';
        $service = new AllRatesToday($this->getHttpAdapterMock($url, '[{"source":"USD","target":"EUR","rate":"n/a"}]'), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    #[Test]
    public function it_throws_an_unsupported_currency_pair_exception_when_rate_is_zero()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';
        $service = new AllRatesToday($this->getHttpAdapterMock($url, '[{"source":"USD","target":"EUR","rate":0}]'), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    #[Test]
    public function it_has_a_name()
    {
        $service = new AllRatesToday($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertSame('allratestoday', $service->getName());
    }
}
