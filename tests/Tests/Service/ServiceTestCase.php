<?php

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
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function getResponse($content)
    {
        $body = $this->createMock('Psr\Http\Message\StreamInterface');
        $body
            ->expects($this->once())
            ->method('__toString')
            ->will($this->returnValue($content));

        $response = $this->createMock('Psr\Http\Message\ResponseInterface');
        $response
            ->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue($body));

        return $response;
    }

    /**
     * Create a mocked Http adapter.
     *
     * @param string $url     The url
     * @param string $content The body content
     *
     * @return \Http\Client\HttpClient
     */
    protected function getHttpAdapterMock($url, $content)
    {
        $response = $this->getResponse($content);

        $adapter = $this->createMock('Http\Client\HttpClient');

        $adapter
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function ($arg) use ($url) {
                return $arg->getUri()->__toString() === $url;
            }))
            ->will($this->returnValue($response));

        return $adapter;
    }
}
