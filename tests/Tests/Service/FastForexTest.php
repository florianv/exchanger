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
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\Service\FastForex;
use PHPUnit\Framework\Attributes\Test;
use Exchanger\Contract\ExchangeRateQuery as ExchangeRateQueryContract;
use Exchanger\Contract\ExchangeRateService;
use Psr\Http\Client\ClientInterface;
use Http\Client\HttpClient;
use Exchanger\Tests\FileContents;

class FastForexTest extends HttpServiceContractTestCase
{
    #[Test]
    public function it_supports_all_queries()
    {
        $service = new FastForex($this->createMock(HttpClient::class), null, ['api_key' => 'secret']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'))));
        $this->assertTrue($service->supportQuery(new HistoricalExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'), new \DateTime())));
    }

    #[Test]
    public function it_throws_an_exception_when_rate_not_supported()
    {
        $this->expectException(Exception::class);
        $url = 'https://api.fastforex.io/fetch-one?from=EUR&to=ZZZ';
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/FastForex/error-unsupported.json');
        $service = new FastForex($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/ZZZ')));
    }

    #[Test]
    public function it_fetches_a_rate_when_response_symbol_matches()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $url = 'https://api.fastforex.io/fetch-one?from=USD&to=EUR';
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/FastForex/one-usd-eur.json');
        $service = new FastForex($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));
        $this->assertSame(0.8258, $rate->getValue());
        $this->assertEquals('2021-02-16', $rate->getDate()->format('Y-m-d'));
        $this->assertEquals('fastforex', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_throws_an_exception_when_response_symbol_does_not_match()
    {
        $this->expectException(Exception::class);
        $url = 'https://api.fastforex.io/fetch-one?from=USD&to=AED';
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/FastForex/one-usd-eur.json');
        $service = new FastForex($this->getHttpAdapterMock($url, $content), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/AED')));
    }

    #[Test]
    public function it_throws_an_exception_when_response_status_error()
    {
        $this->expectException(Exception::class);
        $url = 'https://api.fastforex.io/fetch-one?from=USD&to=AED';
        $service = new FastForex($this->getHttpAdapterMock($url, '', 401), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/AED')));
    }

    #[Test]
    public function it_has_a_name()
    {
        $service = new FastForex($this->createMock(HttpClient::class), null, ['api_key' => 'secret']);

        $this->assertSame('fastforex', $service->getName());
    }

    #[\Override]
    protected function createService(ClientInterface $httpClient, array $options): ExchangeRateService
    {
        return new FastForex($httpClient, null, $options);
    }

    #[\Override]
    protected function validOptions(): array
    {
        return ['api_key' => self::SECRET];
    }

    #[\Override]
    protected function requiredOptions(): array
    {
        return ['api_key'];
    }

    #[\Override]
    protected function supportedQuery(): ExchangeRateQueryContract
    {
        return new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'));
    }

    #[\Override]
    protected function unsupportedHistoricalQuery(): ?ExchangeRateQueryContract
    {
        return null;
    }

    #[\Override]
    protected function successfulResponseBody(): string
    {
        return FileContents::read(__DIR__ . '/../../Fixtures/Service/FastForex/one-usd-eur.json');
    }
}
