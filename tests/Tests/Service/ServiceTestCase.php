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
        $body = $this->createMock('Psr\Http\Message\StreamInterface');
        $body
            ->expects($this->once())
            ->method('__toString')
            ->willReturn($content);

        $response = $this->createMock('Psr\Http\Message\ResponseInterface');
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
     *
     * @return \Http\Client\HttpClient
     */
    protected function getHttpAdapterMock($url, $content, $statusCode = 200)
    {
        $response = $this->getResponse($content, $statusCode);

        $adapter = $this->createMock('Http\Client\HttpClient');

        $adapter
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function ($arg) use ($url) {
                return $arg->getUri()->__toString() === $url;
            }))
            ->willReturn($response);

        return $adapter;
    }
}
