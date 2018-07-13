<?php

/*
 * This file is part of Exchanger.
 *
 * (c) Jonas Hansen <jonas.kerwin.hansen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Service;

use Exchanger\Contract\CurrencyPair;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;
use Psr\Http\Message\ResponseInterface;

/**
 * Exchange Rates Api Service.
 *
 * @author Jonas Hansen <jonas.kerwin.hansen@gmail.com>
 */
class ExchangeRatesApi extends HistoricalService
{
    const LATEST_URL = 'https://exchangeratesapi.io/api/latest?base=%s';

    const HISTORICAL_URL = 'https://exchangeratesapi.io/api/%s?base=%s';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        
        $url = sprintf(
            self::LATEST_URL,
            $currencyPair->getBaseCurrency()
        );

        return $this->createExchangeRateForUrl($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $url = sprintf(
            self::HISTORICAL_URL,
            $exchangeQuery->getDate()->format('Y-m-d'),
            $currencyPair->getBaseCurrency()
        );

        return $this->createExchangeRateForUrl($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        return true;
    }

    /**
     * Create an exchange rate with the results from given url.
     * 
     * @param  string $url
     * @param  \Exchanger\Contract\CurrencyPair $currencyPair
     * @return \Exchanger\Contract\ExchangeRate
     */
    private function createExchangeRateForUrl($url, CurrencyPair $currencyPair)
    {
        $results = $this->fetchResults($url, $currencyPair);

        return $this->createExchangeRate($results, $currencyPair);
    }

    /**
     * Create a exchange rate instance
     * 
     * @param  array $results
     * @param  CurrencyPair $currencyPair
     * @return \Exchanger\Contract\ExchangeRate|null
     */
    private function createExchangeRate(array $results, CurrencyPair $currencyPair)
    {
        if (empty($results['rates']) || $this->missingCurrency($results['rates'], $currencyPair)) {
            return null;
        }

        $date = new \DateTime($results['date']);
        $rate = $results['rates'][$currencyPair->getQuoteCurrency()];

        return new ExchangeRate($rate, $date);
    }

    /**
     * Whether the given rates contains the quoted currency
     * 
     * @param  array $rates
     * @param  \Exchanger\Contract\CurrencyPair $pair
     * @return boolean
     */
    private function missingCurrency(array $rates, CurrencyPair $pair)
    {
        return ! isset($rates[$pair->getQuoteCurrency()]);
    }

    /**
     * Fetch the exchange rates from given url
     * 
     * @param  string $url
     * @throws \Exchanger\Exception\Exception 
     * @return array
     */
    private function fetchResults($url)
    {
        $response = $this->getResponse($url);

        $results = $this->decodeJsonResponse($response);

        if (isset($results['error'])) {
            throw new Exception($results['error']);
        }

        return $results;
    }

    /**
     * Decode the json content of given response
     * 
     * @param  \Psr\Http\Message\ResponseInterface $response
     * @throws \RuntimeException 
     * @return array
     */
    private function decodeJsonResponse(ResponseInterface $response)
    {
        return StringUtil::jsonToArray(
            $response->getBody()->__toString()
        );
    }
}
