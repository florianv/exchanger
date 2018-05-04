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

abstract class ServiceTestCase extends \PHPUnit_Framework_TestCase
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
        $body = $this->getMock('Psr\Http\Message\StreamInterface');
        $body
            ->expects($this->once())
            ->method('__toString')
            ->will($this->returnValue($content));

        $response = $this->getMock('Psr\Http\Message\ResponseInterface');
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

        $adapter = $this->getMock('Http\Client\HttpClient');

        $adapter
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function ($arg) use ($url) {
                return $arg->getUri()->__toString() === $url;
            }))
            ->will($this->returnValue($response));

        return $adapter;
    }

    /**
     * Create a mocked Http adapter for Google service.
     *
     * @param string $url     The url
     * @param string $content The body content
     *
     * @return \Http\Client\HttpClient
     */
    protected function getGoogleHttpAdapterMock($url, $content)
    {
        $response = $this->getResponse($content);

        $adapter = $this->getMock('Http\Client\HttpClient');

        $adapter
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function ($arg) use ($url) {
                return $arg->getUri()->__toString() === $url && $arg->getHeaders() === [
                    'Host' => [0 => 'www.google.com'],
                    'Accept' => [0 => 'text/html'],
                    'User-Agent' => [0 => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:21.0) Gecko/20100101 Firefox/21.0'],
                ];
            }))
            ->will($this->returnValue($response));

        return $adapter;
    }
}
