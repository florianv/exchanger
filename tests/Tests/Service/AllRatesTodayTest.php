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

use Exchanger\Contract\ExchangeRate as ExchangeRateContract;
use Exchanger\Contract\ExchangeRateQuery as ExchangeRateQueryContract;
use Exchanger\Contract\ExchangeRateService;
use Exchanger\CurrencyPair;
use Exchanger\Exception\Exception;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\AllRatesToday;
use Exchanger\Tests\FileContents;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

final class AllRatesTodayTest extends HttpServiceContractTestCase
{
    private const URL = 'https://allratestoday.com/api/v1/rates?source=USD&target=EUR';

    #[Test]
    public function it_fetches_a_rate(): void
    {
        $pair = CurrencyPair::createFromString('USD/EUR');
        $service = new AllRatesToday(
            $this->getHttpAdapterMock(self::URL, $this->fixture('rate-usd-eur'), 200, function (RequestInterface $request): bool {
                return $request->getHeaderLine('Authorization') === 'Bearer secret';
            }),
            null,
            ['api_key' => 'secret'],
        );

        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertSame(0.9215, $rate->getValue());
        $this->assertSame('2026-04-03T12:00:00+00:00', $rate->getDate()->format(\DATE_ATOM));
        $this->assertSame('all_rates_today', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_picks_the_row_of_the_requested_pair(): void
    {
        $body = '[{"rate":0.79,"source":"USD","target":"GBP","time":"2026-04-03T12:00:00Z"},'
            . '{"rate":0.9215,"source":"USD","target":"EUR","time":"2026-04-03T12:00:00Z"}]';

        $this->assertSame(0.9215, $this->fetch($body)->getValue());
    }

    #[Test]
    public function it_sends_uppercase_currency_codes(): void
    {
        $service = new AllRatesToday($this->getHttpAdapterMock(self::URL, $this->fixture('rate-usd-eur')), null, ['api_key' => 'secret']);

        $rate = $service->getExchangeRate(new ExchangeRateQuery(new CurrencyPair('usd', 'eur')));

        $this->assertSame(0.9215, $rate->getValue());
    }

    #[Test]
    public function it_trims_the_api_key(): void
    {
        $service = new AllRatesToday(
            $this->getHttpAdapterMock(self::URL, $this->fixture('rate-usd-eur'), 200, function (RequestInterface $request): bool {
                return $request->getHeaderLine('Authorization') === 'Bearer secret';
            }),
            null,
            ['api_key' => "secret\n"],
        );

        $this->assertSame(0.9215, $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')))->getValue());
    }

    #[Test]
    public function it_rejects_a_blank_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('api_key');

        new AllRatesToday($this->createStub(ClientInterface::class), null, ['api_key' => " \n"]);
    }

    #[Test]
    public function it_reports_the_api_error_message_with_the_status_code(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Authentication required. Provide an API key via Authorization header or log in. (HTTP response code 401)');

        $this->fetch($this->fixture('error-authentication-required'), 401);
    }

    #[Test]
    public function it_reports_the_request_limit_as_a_service_error(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Request limit exceeded (HTTP response code 429)');

        $this->fetch($this->fixture('error-request-limit-exceeded'), 429);
    }

    #[Test]
    public function it_reports_an_error_field_in_a_200_response(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Quota exceeded');

        $this->fetch('{"error":"Quota exceeded"}');
    }

    #[Test]
    public function it_keeps_the_status_code_when_the_error_body_is_not_json(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to parse response (HTTP response code 502)');

        $this->fetch('<html>Bad Gateway</html>', 502);
    }

    #[Test]
    public function it_reports_http_400_as_an_unsupported_pair(): void
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $url = 'https://allratestoday.com/api/v1/rates?source=USD&target=XYZ';
        $service = new AllRatesToday($this->getHttpAdapterMock($url, '{"error":"Bad Request"}', 400), null, ['api_key' => 'secret']);

        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/XYZ')));
    }

    #[Test]
    public function it_reports_an_empty_list_as_an_unsupported_pair(): void
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $this->fetch('[]');
    }

    #[Test]
    public function it_does_not_return_a_rate_for_another_base_currency(): void
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $this->fetch('[{"rate":1.08,"source":"GBP","target":"EUR","time":"2026-04-03T12:00:00Z"}]');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMalformedRows(): iterable
    {
        yield 'object instead of a list' => ['{"message":"maintenance"}'];
        yield 'rows wrapped in an object' => ['{"data":[{"rate":0.9215,"source":"USD","target":"EUR","time":"2026-04-03T12:00:00Z"}]}'];
        yield 'row without currency codes' => ['[{"rate":0.9215,"time":"2026-04-03T12:00:00Z"}]'];
        yield 'non-numeric rate' => ['[{"rate":"n/a","source":"USD","target":"EUR","time":"2026-04-03T12:00:00Z"}]'];
        yield 'null rate' => ['[{"rate":null,"source":"USD","target":"EUR","time":"2026-04-03T12:00:00Z"}]'];
        yield 'zero rate' => ['[{"rate":0,"source":"USD","target":"EUR","time":"2026-04-03T12:00:00Z"}]'];
        yield 'missing time' => ['[{"rate":0.9215,"source":"USD","target":"EUR"}]'];
        yield 'invalid time' => ['[{"rate":0.9215,"source":"USD","target":"EUR","time":"not a date"}]'];
    }

    #[Test]
    #[DataProvider('provideMalformedRows')]
    public function it_reports_malformed_rows_as_service_errors(string $body): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unexpected response');

        $this->fetch($body);
    }

    #[\Override]
    protected function createService(ClientInterface $httpClient, array $options): ExchangeRateService
    {
        return new AllRatesToday($httpClient, null, $options);
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
        return new HistoricalExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'), new \DateTime('2024-01-15'));
    }

    #[\Override]
    protected function successfulResponseBody(): string
    {
        return $this->fixture('rate-usd-eur');
    }

    private function fetch(string $body, int $statusCode = 200): ExchangeRateContract
    {
        $service = new AllRatesToday($this->getHttpAdapterMock(self::URL, $body, $statusCode), null, ['api_key' => 'secret']);

        return $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    private function fixture(string $name): string
    {
        return FileContents::read(__DIR__ . '/../../Fixtures/Service/AllRatesToday/' . $name . '.json');
    }
}
