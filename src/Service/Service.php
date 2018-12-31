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

use Exchanger\Contract\ExchangeRateService;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for http based services.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
abstract class Service implements ExchangeRateService
{
    /**
     * The client.
     *
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * The request factory.
     *
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * The options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * @param ClientInterface|null         $httpClient
     * @param RequestFactoryInterface|null $requestFactory
     * @param array                        $options
     */
    public function __construct(ClientInterface $httpClient = null, RequestFactoryInterface $requestFactory = null, array $options = [])
    {
        $this->httpClient = $httpClient ?: Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?: Psr17FactoryDiscovery::findRequestFactory();

        $this->processOptions($options);
        $this->options = $options;
    }

    /**
     * Processes the service options.
     *
     * @param array &$options
     */
    public function processOptions(array &$options): void
    {
    }

    /**
     * @param string $url
     * @param array  $headers
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    private function buildRequest($url, array $headers = []): RequestInterface
    {
        $request = $this->requestFactory->createRequest('GET', $url);
        foreach ($headers as $header => $value) {
            $request = $request->withHeader($header, $value);
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
}
