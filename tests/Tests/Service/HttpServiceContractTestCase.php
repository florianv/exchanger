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
use Http\Mock\Client;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Client\ClientInterface;

/**
 * Behaviour every HTTP-based exchange rate service must share.
 *
 * Extend it from the service test and implement the abstract methods. To opt out of a test,
 * list it in $skippedTests with the reason; the reason is shown in the test output.
 */
abstract class HttpServiceContractTestCase extends ServiceTestCase
{
    /**
     * Value for secret options (API keys), so the tests can check that it never leaks into error messages.
     */
    protected const SECRET = 'contract-test-secret';

    /**
     * @var array<string, string> contract test method name => reason
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
     * Option keys the constructor must reject when missing. Return [] for a service that needs no options.
     *
     * @return list<string>
     */
    abstract protected function requiredOptions(): array;

    /**
     * A query the service supports, answered by successfulResponseBody().
     */
    abstract protected function supportedQuery(): ExchangeRateQuery;

    /**
     * A real response body from the provider for supportedQuery().
     */
    abstract protected function successfulResponseBody(): string;

    /**
     * A historical query supportQuery() rejects, or null if the service supports every date.
     */
    protected function unsupportedHistoricalQuery(): ?ExchangeRateQuery
    {
        return new HistoricalExchangeRateQuery($this->supportedQuery()->getCurrencyPair(), new \DateTimeImmutable('2000-01-03'));
    }

    #[Test]
    public function it_skips_only_contract_tests_and_explains_why(): void
    {
        foreach ($this->skippedTests as $test => $reason) {
            $this->assertTrue(
                str_starts_with($test, 'it_') && method_exists(self::class, $test),
                \sprintf('$skippedTests lists "%s", which is not a test of %s.', $test, self::class),
            );
            $this->assertNotSame('', trim($reason), \sprintf('$skippedTests must give a reason for skipping "%s".', $test));
        }

        $this->addToAssertionCount(1);
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

        $this->assertServiceError($exception);
        $this->assertStringContainsString(
            (string) $statusCode,
            $exception->getMessage(),
            'Include the HTTP status code in the message, so an outage is distinguishable from a provider error.',
        );
    }

    #[Test]
    #[DataProvider('provideMalformedSuccessfulBodies')]
    public function it_reports_malformed_responses_as_service_errors(string $body): void
    {
        $this->skipIfListed(__FUNCTION__);

        $this->assertServiceError($this->catchException($this->createServiceRespondingWith(200, $body), $this->supportedQuery()));
    }

    #[Test]
    public function it_rejects_historical_queries_it_does_not_support(): void
    {
        $this->skipIfListed(__FUNCTION__);

        $query = $this->unsupportedHistoricalQuery();
        $service = $this->createServiceRespondingWith(200, $this->successfulResponseBody());

        if (null === $query || $service->supportQuery($query)) {
            $this->markTestSkipped('The service supports every historical date. Override unsupportedHistoricalQuery() if some dates are not supported.');
        }

        $exception = $this->catchException($service, $query);

        $this->assertTrue(
            $exception instanceof UnsupportedDateException || $exception instanceof UnsupportedExchangeQueryException,
            \sprintf(
                'getExchangeRate() must throw UnsupportedDateException or UnsupportedExchangeQueryException for a query supportQuery() rejects, %s thrown: %s',
                $exception::class,
                $exception->getMessage(),
            ),
        );
    }

    #[Test]
    public function it_requires_its_options(): void
    {
        $this->skipIfListed(__FUNCTION__);

        $requiredOptions = $this->requiredOptions();

        $this->assertSame(
            [],
            array_values(array_diff($requiredOptions, array_keys($this->validOptions()))),
            'Every option in requiredOptions() must also be set in validOptions().',
        );

        if ([] === $requiredOptions) {
            $this->assertInstanceOf(ExchangeRateService::class, $this->createService(new Client(), []), 'requiredOptions() is empty, so the service must work without options.');

            return;
        }

        foreach ($requiredOptions as $option) {
            $options = $this->validOptions();
            unset($options[$option]);

            try {
                $this->createService(new Client(), $options);
                $this->fail(\sprintf('The constructor must reject a missing "%s" option.', $option));
            } catch (\InvalidArgumentException|NonBreakingInvalidArgumentException $exception) {
                $this->assertStringContainsString($option, $exception->getMessage(), 'Name the missing option in the message.');
            }
        }
    }

    public static function provideHttpErrors(): iterable
    {
        yield 'empty 500' => [500, ''];
        yield 'HTML 502 from a gateway' => [502, '<html><body><h1>502 Bad Gateway</h1></body></html>'];
        yield 'JSON 503 with a message field' => [503, '{"message":"Service Unavailable"}'];
        yield 'JSON 503 with an error field' => [503, '{"error":"Service Unavailable"}'];
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

    /**
     * A transport or parsing failure must be a deliberate service error: not a PHP error, not an
     * "unsupported" answer (Chain callers and users treat those as permanent), and without the API key.
     */
    private function assertServiceError(\Throwable $exception): void
    {
        $this->assertNotInstanceOf(
            \Error::class,
            $exception,
            \sprintf('A failed request must be handled, not crash with %s: %s', $exception::class, $exception->getMessage()),
        );

        foreach ([UnsupportedCurrencyPairException::class, UnsupportedDateException::class, UnsupportedExchangeQueryException::class] as $class) {
            $this->assertNotInstanceOf(
                $class,
                $exception,
                'A transport or parsing failure must not look like an unsupported pair or date: Chain callers and users treat those as permanent.',
            );
        }

        $this->assertStringNotContainsString(self::SECRET, $exception->getMessage(), 'Error messages must not contain API keys.');
    }

    private function skipIfListed(string $test): void
    {
        if (isset($this->skippedTests[$test])) {
            $this->markTestSkipped($this->skippedTests[$test]);
        }
    }
}
