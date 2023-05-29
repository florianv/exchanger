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

class HttpServiceTest extends TestCase
{
    /**
     * @test
     */
    public function initialize_with_http_client()
    {
        if (false == interface_exists(ClientInterface::class)) {
            $this->markTestSkipped('PSR-18 client is not required.');
        }

        $httpClient = $this->createMock('Psr\Http\Client\ClientInterface');
        $this->expectNotToPerformAssertions();
        $this->createAnonymousClass($httpClient);
    }

    /**
     * @test
     */
    public function initialize_with_null_as_client()
    {
        $this->expectException(\Http\Discovery\Exception\NotFoundException::class);
        $this->expectExceptionMessage('No PSR-18 clients found. Make sure to install a package providing "psr/http-client-implementation". Example: "php-http/guzzle7-adapter"');
        $this->createAnonymousClass(null);
    }

    /**
     * @test
     */
    public function initialize_with_invalid_client()
    {
        $httpClient = new \stdClass();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Client must be an instance of Psr\Http\Client\ClientInterface');
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
