<?php

namespace Exchanger\Service;

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;

/**
 * Forge Service
 */
class Forge extends Service {

    const URL = 'https://forex.1forge.com/latest/quotes?pairs=%s&api_key=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options) {
        if (!isset($options['api_key'])) {
            throw new \InvalidArgumentException('The "api_key" option must be provided.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery) {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeRateQuery) {
        $currencyPair = $exchangeRateQuery->getCurrencyPair();
        $url = sprintf(self::URL, $currencyPair->getBaseCurrency() . $currencyPair->getQuoteCurrency(), $this->options['api_key']);

        $content = $this->request($url);

        $data = StringUtil::jsonToArray($content);

        $date = (new \DateTime())->setTimestamp($data[0]['timestamp']);

        if (!empty($data)) {
            return new ExchangeRate($data[0]['price'], $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

}
