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
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;

/**
 * Yahoo Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 *
 * @deprecated The service was discontinued by Yahoo without notice
 */
class Yahoo extends Service
{
    const URL = 'https://query.yahooapis.com/v1/public/yql?q=%s&env=store://datatables.org/alltableswithkeys&format=json';

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $queryPairs = sprintf('"%s%s"', $currencyPair->getBaseCurrency(), $currencyPair->getQuoteCurrency());
        $query = sprintf('select * from yahoo.finance.xchange where pair in (%s)', $queryPairs);
        $url = sprintf(self::URL, urlencode($query));

        $content = $this->request($url);

        $json = StringUtil::jsonToArray($content);

        if (isset($json['error'])) {
            throw new Exception($json['error']['description']);
        }

        $data = $json['query']['results']['rate'];

        if ('0.00' === $data['Rate'] || 'N/A' === $data['Date']) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $dateString = $data['Date'].' '.$data['Time'];

        if (!$date = \DateTime::createFromFormat('m/d/Y H:ia', $dateString)) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        return new ExchangeRate($data['Rate'], $date);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        return !$exchangeQuery instanceof HistoricalExchangeRateQuery;
    }
}
