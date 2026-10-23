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

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\ExchangeRateService;
use Exchanger\Exception\NonBreakingInvalidArgumentException;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\Exception\UnsupportedExchangeQueryException;
use Exchanger\HistoricalExchangeRateQuery;
use Psr\Http\Client\ClientInterface;
use Http\Mock\Client;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Behaviour every HTTP-based exchange rate service must share.
 *
 * Extend it from the service test and implement the abstract methods. To opt out of a test,
 * list it in $skippedTests with the reason; reviewers see the reason in the test output.
 */
abstract class HttpServiceContractTestCase extends ServiceTestCase
{
    /**
     * Value for secret options (API keys), so the tests can check that it never leaks into error messages.
     */
    protected const SECRET = 'contract-test-secret';

    /**
     * @var array<string, string> test method name => reason
     */
    protected array $skippedTests = [];

    /**
     * Creates the service with the given HTTP client and options.
     */
    abstract protected function createService(ClientInterface $httpClient, array $options): ExchangeRateService;

    /**
     * Options that make the service usable. Use self::SECRET for API keys.
     */
    abstract protected function validOptions(): array;

    /**
     * A query the service supports, answered by successfulResponseBody().
     */
    abstract protected function supportedQuery(): ExchangeRateQuery;

    /**
     * A real response body from the provider for supportedQuery().
     */
    abstract protected function successfulResponseBody(): string;

    /**
     * Option keys the constructor must reject when missing.
     *
     * @return list<string>
     */
    protected function requiredOptions(): array
    {
        return [];
    }

    #[Test]
    public function it_returns_a_positive_rate_from_a_successful_response(): void
    {
        $this->skipIfListed(__FUNCTION__);

        $query = $this->supportedQuery();
        $service = $this->createServiceRespondingWith(200, $this->successfulResponseBody());

        $this->assertTrue($service->supportQuery($query), 'supportedQuery() must return a query the service supports.');

        $rate = $service->getExchangeRate($query);

        $this->assertGreaterThan(0, $rate->getValue());
        $this->assertSame($service->getName(), $rate->getProviderName());
        $this->assertSame($query->getCurrencyPair(), $rate->getCurrencyPair());
    }

    #[Test]
    #[DataProvider('provideHttpErrors')]
    public function it_reports_http_errors_as_service_errors(int $statusCode, string $body): void
    {
        $this->skipIfListed(__FUNCTION__);

        $exception = $this->catchException($this->createServiceRespondingWith($statusCode, $body), $this->supportedQuery());

        $this->assertNotUnsupportedException($exception);
        $this->assertStringContainsString(
            (string) $statusCode,
            $exception->getMessage(),
            'Include the HTTP status code in the message, so an outage is distinguishable from a provider error.',
        );
        $this->assertStringNotContainsString(self::SECRET, $exception->getMessage(), 'Error messages must not contain API keys.');
    }

    #[Test]
    #[DataProvider('provideMalformedSuccessfulBodies')]
    public function it_reports_malformed_responses_as_service_errors(string $body): void
    {
        $this->skipIfListed(__FUNCTION__);

        $exception = $this->catchException($this->createServiceRespondingWith(200, $body), $this->supportedQuery());

        $this->assertNotUnsupportedException($exception);
        $this->assertStringNotContainsString(self::SECRET, $exception->getMessage(), 'Error messages must not contain API keys.');
    }

    #[Test]
    public function it_does_not_answer_historical_queries_it_does_not_support(): void
    {
        $this->skipIfListed(__FUNCTION__);

        $query = new HistoricalExchangeRateQuery($this->supportedQuery()->getCurrencyPair(), new \DateTimeImmutable('2000-01-03'));
        $service = $this->createServiceRespondingWith(200, $this->successfulResponseBody());

        if ($service->supportQuery($query)) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertInstanceOf(
            \Throwable::class,
            $this->catchException($service, $query),
            'getExchangeRate() must not return a rate for a query supportQuery() rejects.',
        );
    }

    #[Test]
    public function it_requires_its_options(): void
    {
        $this->skipIfListed(__FUNCTION__);

        foreach ($this->requiredOptions() as $option) {
            $options = $this->validOptions();
            unset($options[$option]);

            try {
                $this->createService(new Client(), $options);
                $this->fail(\sprintf('The constructor must reject a missing "%s" option.', $option));
            } catch (\InvalidArgumentException|NonBreakingInvalidArgumentException $exception) {
                $this->assertStringContainsString($option, $exception->getMessage(), 'Name the missing option in the message.');
            }
        }

        $this->addToAssertionCount(1);
    }

    public static function provideHttpErrors(): iterable
    {
        yield 'empty 500' => [500, ''];
        yield 'HTML 502 from a gateway' => [502, '<html><body><h1>502 Bad Gateway</h1></body></html>'];
        yield 'JSON 503' => [503, '{"message":"Service Unavailable"}'];
        yield 'plain-text 429' => [429, 'Too Many Requests'];
    }

    public static function provideMalformedSuccessfulBodies(): iterable
    {
        yield 'empty body' => [''];
        yield 'HTML page' => ['<html><body>Maintenance</body></html>'];
        yield 'truncated JSON' => ['{"rates": {'];
    }

    protected function createServiceRespondingWith(int $statusCode, string $body): ExchangeRateService
    {
        $httpClient = new Client();
        $httpClient->setDefaultResponse(new Response($statusCode, [], $body));

        return $this->createService($httpClient, $this->validOptions());
    }

    private function catchException(ExchangeRateService $service, ExchangeRateQuery $query): \Throwable
    {
        try {
            $service->getExchangeRate($query);
        } catch (\Throwable $exception) {
            return $exception;
        }

        $this->fail('Expected getExchangeRate() to throw.');
    }

    private function assertNotUnsupportedException(\Throwable $exception): void
    {
        foreach ([UnsupportedCurrencyPairException::class, UnsupportedDateException::class, UnsupportedExchangeQueryException::class] as $class) {
            $this->assertNotInstanceOf(
                $class,
                $exception,
                'A transport or parsing failure must not look like an unsupported pair or date: Chain callers and users treat those as permanent.',
            );
        }
    }

    private function skipIfListed(string $test): void
    {
        if (isset($this->skippedTests[$test])) {
            $this->markTestSkipped($this->skippedTests[$test]);
        }
    }
}
