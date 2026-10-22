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
    public function it_throws_an_exception_when_response_status_error()
    {
        $this->expectException(Exception::class);

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';
        $service = new AllRatesToday($this->getHttpAdapterMock($url, '', 500), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
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
    public function it_has_a_name()
    {
        $service = new AllRatesToday($this->createMock('Http\Client\HttpClient'), null, ['api_key' => 'secret']);

        $this->assertSame('allratestoday', $service->getName());
    }
}
