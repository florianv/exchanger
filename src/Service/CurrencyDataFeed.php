<?php

namespace Exchanger\Service;

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\ExchangeRate;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\StringUtil;
use Exchanger\Exception\UnsupportedCurrencyPairException;

/**
 * CurrencyDataFeed Service.
 */
class CurrencyDataFeed extends Service
{
    const URL = 'https://currencydatafeed.com/api/data.php?token=%s&currency=%s';

    const HISTORICAL_URL = 'https://currencydatafeed.com/api/historical.php?token=%s&date=%s&currency=%s';

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
        $url = sprintf(self::URL, $this->options['api_key'], $currencyPair->getBaseCurrency().'/'.$currencyPair->getQuoteCurrency());

        $content = $this->request($url);

        $data = StringUtil::jsonToArray($content);

        if (!empty($data) && $data['status'] && !isset($data['currency'][0]['error'])) {
            $date = (new \DateTime())->setTimestamp(strtotime($data['currency'][0]['date']));

            return new ExchangeRate($data['currency'][0]['value'], $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }
}
