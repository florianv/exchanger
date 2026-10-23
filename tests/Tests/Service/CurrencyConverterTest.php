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
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\CurrencyConverter;
use Http\Client\HttpClient;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\Attributes\Test;
use Exchanger\Contract\ExchangeRateQuery as ExchangeRateQueryContract;
use Exchanger\Contract\ExchangeRateService;
use Psr\Http\Client\ClientInterface;
use Exchanger\Tests\FileContents;

class CurrencyConverterTest extends HttpServiceContractTestCase
{
    #[Test]
    public function it_throws_an_exception_if_access_key_option_missing_in_enterprise_mode()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "access_key" option must be provided.');
        new CurrencyConverter($this->createMock(HttpClient::class), null, ['enterprise' => true]);
    }

    #[Test]
    public function it_throws_an_exception_with_error_response()
    {
        $this->expectException(Exception::class);
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/CurrencyConverter/error.json');

        $service = new CurrencyConverter($this->getAnyUrlHttpAdapterMock($content, 200), null, ['access_key' => 'secret']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('XXX/YYY')));
    }

    #[Test]
    public function it_fetches_a_rate_normal_mode()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/CurrencyConverter/success.json');

        $service = new CurrencyConverter($this->getAnyUrlHttpAdapterMock($content, 200), null, ['access_key' => 'secret']);
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.726804, $rate->getValue());
        $this->assertEquals('currency_converter', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_a_rate_enterprise_mode()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/CurrencyConverter/success.json');

        $service = new CurrencyConverter($this->getAnyUrlHttpAdapterMock($content, 200), null, ['access_key' => 'secret', 'enterprise' => true]);
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.726804, $rate->getValue());
        $this->assertEquals('currency_converter', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_a_historical_rate_normal_mode()
    {
        // The free plan only serves the past year.
        $day = (new \DateTime('-1 month'))->format('Y-m-d');
        $pair = CurrencyPair::createFromString('USD/EUR');
        $content = str_replace('2017-01-01', $day, FileContents::read(__DIR__ . '/../../Fixtures/Service/CurrencyConverter/historical_success.json'));
        $date = new \DateTime($day . ' UTC');

        $service = new CurrencyConverter($this->getAnyUrlHttpAdapterMock($content, 200), null, ['access_key' => 'secret']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertSame(0.726804, $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('currency_converter', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_a_historical_rate_enterprise_mode()
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $content = FileContents::read(__DIR__ . '/../../Fixtures/Service/CurrencyConverter/historical_success.json');
        $date = new \DateTime('2017-01-01 UTC');

        $service = new CurrencyConverter($this->getAnyUrlHttpAdapterMock($content, 200), null, ['access_key' => 'secret', 'enterprise' => true]);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertSame(0.726804, $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('currency_converter', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_has_a_name()
    {
        $service = new CurrencyConverter($this->createMock(HttpClient::class), null, ['access_key' => 'secret']);

        $this->assertSame('currency_converter', $service->getName());
    }

    /**
     * Create a mocked Http adapter that accepts any URL.
     *
     * @param string $content    The body content
     * @param int    $statusCode HTTP status code
     *
     * @return HttpClient
     */
    protected function getAnyUrlHttpAdapterMock($content, $statusCode = 200)
    {
        $response = $this->getResponse($content, $statusCode);

        $adapter = $this->createMock(HttpClient::class);

        $adapter
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        return $adapter;
    }

    /**
     * Create a mocked Response.
     *
     * @param string $content    The body content
     * @param int    $statusCode HTTP status code
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function getResponse($content, $statusCode)
    {
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('__toString')
            ->willReturn($content);

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($body);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($statusCode);

        return $response;
    }

    #[\Override]
    protected function createService(ClientInterface $httpClient, array $options): ExchangeRateService
    {
        return new CurrencyConverter($httpClient, null, $options);
    }

    #[\Override]
    protected function validOptions(): array
    {
        return ['access_key' => self::SECRET];
    }

    #[\Override]
    protected function requiredOptions(): array
    {
        return ['access_key'];
    }

    #[\Override]
    protected function supportedQuery(): ExchangeRateQueryContract
    {
        return new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'));
    }

    #[\Override]
    protected function successfulResponseBody(): string
    {
        return FileContents::read(__DIR__ . '/../../Fixtures/Service/CurrencyConverter/success.json');
    }
}
