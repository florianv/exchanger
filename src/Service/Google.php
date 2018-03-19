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
 */
class Google extends Service
{
    const URL = 'https://www.google.es/search?q=1+%s+to+%s';

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $url = sprintf(self::URL, $currencyPair->getBaseCurrency(), $currencyPair->getQuoteCurrency());

        $content = $this->request($url, [
            'Accept' => 'text/html',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:21.0) Gecko/20100101 Firefox/21.0'
        ]);

        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);

        $document = new \DOMDocument();

        if (false === @$document->loadHTML($content)) {
            throw new Exception('The page content is not loadable');
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//div[@class="vk_ans vk_bk"]');

        if (1 !== $nodes->length) {
            throw new Exception('The currency is not supported or Google changed the response format');
        }

        $nodeContent = $nodes->item(0)->textContent;
        // Beware of "3 417.36111 Colombian pesos", with a non breaking space
        $nodeContent = strtr($nodeContent,["\xc2\xa0" => '']);
        $bid = strstr($nodeContent, ' ', true);

        if (!is_numeric($bid)) {
            throw new Exception('The currency is not supported or Google changed the response format');
        }

        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

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
