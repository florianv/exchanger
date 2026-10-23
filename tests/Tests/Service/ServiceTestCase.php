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

use PHPUnit\Framework\TestCase;
use Http\Client\HttpClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

abstract class ServiceTestCase extends TestCase
{
    /**
     * Create a mocked Response.
     *
     * @param string $content The body content
     * @param int $statusCode The status code
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function getResponse($content, $statusCode = 200)
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
            ->method('getStatusCode')
            ->willReturn($statusCode);

        return $response;
    }

    /**
     * Create a mocked Http adapter.
     *
     * @param string $url     The url
     * @param string $content The body content
     * @param int $statusCode The status code
     * @param callable|null $requestCallback Optional extra check receiving the \Psr\Http\Message\RequestInterface,
     *                                       rejecting the request unless it returns a truthy value
     *
     * @return \Http\Client\HttpClient
     */
    protected function getHttpAdapterMock($url, $content, $statusCode = 200, ?callable $requestCallback = null)
    {
        $response = $this->getResponse($content, $statusCode);

        $adapter = $this->createMock(HttpClient::class);

        $adapter
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function ($arg) use ($url, $requestCallback) {
                if ($arg->getUri()->__toString() !== $url) {
                    return false;
                }

                return null === $requestCallback || (bool) $requestCallback($arg);
            }))
            ->willReturn($response);

        return $adapter;
    }
}
