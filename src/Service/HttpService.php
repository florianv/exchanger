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

namespace Exchanger\Service;

use Http\Client\HttpClient;
use Http\Discovery\Psr18ClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Base class for http based services.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
abstract class HttpService extends Service
{
    /**
     * The client.
     *
     * @var HttpClient|ClientInterface
     */
    private $httpClient;

    /**
     * The request factory.
     *
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * The stream factory.
     *
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @param HttpClient|ClientInterface|null $httpClient
     * @param RequestFactoryInterface|null    $requestFactory
     * @param array                           $options
     * @param StreamFactoryInterface|null     $streamFactory
     */
    public function __construct($httpClient = null, ?RequestFactoryInterface $requestFactory = null, array $options = [], ?StreamFactoryInterface $streamFactory = null)
    {
        if (null === $httpClient) {
            $httpClient = Psr18ClientDiscovery::find();
        } else {
            if (!$httpClient instanceof ClientInterface && !$httpClient instanceof HttpClient) {
                throw new \LogicException('Client must be an instance of Http\\Client\\HttpClient or Psr\\Http\\Client\\ClientInterface');
            }
        }

        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory ?: Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?: Psr17FactoryDiscovery::findStreamFactory();

        parent::__construct($options);
    }

    /**
     * @param string      $url
     * @param array       $headers
     * @param string      $method
     * @param string|null $body
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    private function buildRequest($url, array $headers = [], string $method = 'GET', ?string $body = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $header => $value) {
            $request = $request->withHeader($header, $value);
        }

        $request = $request->withHeader('User-Agent', 'Swap');

        if (null !== $body) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        return $request;
    }

    /**
     * Fetches the content of the given url.
     *
     * @param string $url
     * @param array  $headers
     *
     * @return string
     */
    protected function request($url, array $headers = []): string
    {
        return $this->getResponse($url, $headers)->getBody()->__toString();
    }

    /**
     * Fetches the content of the given url.
     *
     * @param string $url
     * @param array  $headers
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function getResponse($url, array $headers = []): ResponseInterface
    {
        return $this->httpClient->sendRequest($this->buildRequest($url, $headers));
    }

    /**
     * Posts the given body to the given url and returns the response content.
     *
     * @param string $url
     * @param string $body
     * @param array  $headers
     *
     * @return string
     */
    protected function postRequest(string $url, string $body, array $headers = []): string
    {
        return $this->httpClient->sendRequest($this->buildRequest($url, $headers, 'POST', $body))->getBody()->__toString();
    }
}
