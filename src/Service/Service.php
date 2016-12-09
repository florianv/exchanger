<?php

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
use Exchanger\Contract\ExchangeRateService;

/**
 * Base class for services.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
abstract class Service implements ExchangeRateService
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
     * The options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * @param HttpClient|null     $httpClient
     * @param RequestFactory|null $requestFactory
     * @param array               $options
     */
    public function __construct(HttpClient $httpClient = null, RequestFactory $requestFactory = null, array $options = [])
    {
        $this->httpClient = $httpClient ?: HttpClientDiscovery::find();
        $this->requestFactory = $requestFactory ?: MessageFactoryDiscovery::find();

        $this->processOptions($options);
        $this->options = $options;
    }

    /**
     * Processes the service options.
     *
     * @param array &$options
     *
     * @return array
     */
    public function processOptions(array &$options)
    {
    }

    /**
     * Fetches the content of the given url.
     *
     * @param string $url
     * @param array $headers
     *
     * @return string
     */
    protected function request($url, array $headers = [])
    {
        $request = $this->requestFactory->createRequest('GET', $url, $headers);

        return $this->httpClient->sendRequest($request)->getBody()->__toString();
    }
}
