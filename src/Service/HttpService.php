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
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\RequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
     * @var HttpClient
     */
    private $httpClient;

    /**
     * The request factory.
     *
     * @var RequestFactory
     */
    private $requestFactory;

    /**
     * @param HttpClient|null     $httpClient
     * @param RequestFactory|null $requestFactory
     * @param array               $options
     */
    public function __construct(HttpClient $httpClient = null, RequestFactory $requestFactory = null, array $options = [])
    {
        $this->httpClient = $httpClient ?: HttpClientDiscovery::find();
        $this->requestFactory = $requestFactory ?: MessageFactoryDiscovery::find();

        parent::__construct($options);
    }

    /**
     * @param string $url
     * @param array  $headers
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    private function buildRequest($url, array $headers = []): RequestInterface
    {
        return $this->requestFactory->createRequest('GET', $url, $headers);
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
}
