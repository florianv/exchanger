<?php

namespace Exchanger\Service;

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\ExchangeRate;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\StringUtil;
use Exchanger\Exception\UnsupportedCurrencyPairException;

/**
 * Forge Service.
 */
class Forge extends Service
{
    const URL = 'https://forex.1forge.com/latest/quotes?pairs=%s&api_key=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options)
    {
        if (!isset($options['api_key'])) {
            throw new \InvalidArgumentException('The "api_key" option must be provided.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        return !$exchangeQuery instanceof HistoricalExchangeRateQuery;
    }

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeRateQuery)
    {
        $currencyPair = $exchangeRateQuery->getCurrencyPair();
        $url = sprintf(self::URL, $currencyPair->getBaseCurrency().$currencyPair->getQuoteCurrency(), $this->options['api_key']);

        $content = $this->request($url);

        $data = StringUtil::jsonToArray($content);

        if (!empty($data)) {
            $date = (new \DateTime())->setTimestamp($data[0]['timestamp']);

            return new ExchangeRate($data[0]['price'], $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }
}
