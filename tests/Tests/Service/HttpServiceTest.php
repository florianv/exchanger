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

use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Service\HttpService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use PHPUnit\Framework\Attributes\Test;

class HttpServiceTest extends TestCase
{
    #[Test]
    public function initialize_with_http_client()
    {
        if (false == interface_exists(ClientInterface::class)) {
            $this->markTestSkipped('PSR-18 client is not required.');
        }

        $httpClient = $this->createMock('Psr\Http\Client\ClientInterface');
        $this->expectNotToPerformAssertions();
        $this->createAnonymousClass($httpClient);
    }

    #[Test]
    public function initialize_with_httplug_client()
    {
        $httpClient = $this->createMock('Http\Client\HttpClient');
        $this->expectNotToPerformAssertions();
        $this->createAnonymousClass($httpClient);
    }

    #[Test]
    public function initialize_with_null_as_client()
    {
        // When null is passed, HttpClientDiscovery auto-discovers a client.
        // php-http/mock-client (a dev dependency) provides client-implementation,
        // so discovery succeeds and no exception is thrown.
        $this->expectNotToPerformAssertions();
        $this->createAnonymousClass(null);
    }

    #[Test]
    public function initialize_with_invalid_client()
    {
        $httpClient = new \stdClass();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Client must be an instance of Http\Client\HttpClient or Psr\Http\Client\ClientInterface');
        $this->createAnonymousClass($httpClient);
    }

    private function createAnonymousClass($httpClient)
    {
        return new class($httpClient) extends HttpService {
            public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
            {
                return new \Exchanger\ExchangeRate(
                    $exchangeQuery->getCurrencyPair(),
                    1,
                    new \DateTimeImmutable(),
                    $this->getName()
                );
            }

            public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
            {
                return true;
            }

            public function getName(): string
            {
                return 'mock';
            }
        };
    }
}
