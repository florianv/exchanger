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

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\ExchangeRate;

/**
 * Google Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 *
 * @deprecated The service is not very reliable and should be used carefully
 */
class Google extends Service
{
    const URL = 'https://www.google.com/search?q=1+%s+to+%s';

    /**
     * The request headers.
     *
     * @var array
     */
    private static $headers = [
        'Accept' => 'text/html',
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:21.0) Gecko/20100101 Firefox/21.0',
    ];

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $url = sprintf(self::URL, $currencyPair->getBaseCurrency(), $currencyPair->getQuoteCurrency());

        $response = $this->getResponse($url, self::$headers);

        // Google may? redirect to your national domain
        if (302 === $response->getStatusCode()) {
            $response = $this->getResponse($response->getHeader('Location')[0], self::$headers);
        }

        $content = $response->getBody()->__toString();

        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);

        try {
            $rate = $this->buildExchangeRate($content);

            libxml_use_internal_errors($internalErrors);
            libxml_disable_entity_loader($disableEntities);
        } catch (\Exception $e) {
            libxml_use_internal_errors($internalErrors);
            libxml_disable_entity_loader($disableEntities);

            throw $e;
        }

        return $rate;
    }

    /**
     * Builds an exchange rate from the response content.
     *
     * @param string $content
     *
     * @return ExchangeRate
     *
     * @throws \Exception
     */
    private function buildExchangeRate($content)
    {
        $document = new \DOMDocument();

        if (false === @$document->loadHTML('<?xml encoding="utf-8" ?>'.$content)) {
            throw new Exception('The page content is not loadable');
        }

        $xpath = new \DOMXPath($document);

        $nodes = $xpath->query('//span[@id="knowledge-currency__tgt-amount"]');

        if (1 !== $nodes->length) {
            $nodes = $xpath->query('//div[@class="vk_ans vk_bk" or @class="dDoNo vk_bk"]');
        }

        if (1 !== $nodes->length) {
            throw new Exception('The currency is not supported or Google changed the response format');
        }

        $nodeContent = $nodes->item(0)->textContent;

        // Beware of "3 417.36111 Colombian pesos", with a non breaking space
        $bid = strtr($nodeContent, ["\xc2\xa0" => '']);

        if (false !== strpos($bid, ' ')) {
            $bid = strstr($bid, ' ', true);
        }

        // Does it have thousands separator?
        if (strpos($bid, ',') && strpos($bid, '.')) {
            $bid = str_replace(',', '', $bid);
        }

        if (!is_numeric($bid)) {
            throw new Exception('The currency is not supported or Google changed the response format');
        }

        return new ExchangeRate($bid, new \DateTime());
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        return !$exchangeQuery instanceof HistoricalExchangeRateQuery;
    }
}
