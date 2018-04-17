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

use Exchanger\Contract\CurrencyPair;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;

/**
 * Fixer Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class Fixer extends HistoricalService
{
    const ACCESS_KEY_OPTION = 'access_key';

    const LATEST_URL = 'https://api.fixer.io/latest?base=%s&access_key=%s';
    const HISTORICAL_URL = 'https://api.fixer.io/%s?base=%s&access_key=%s';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $accessKey = $this->options[self::ACCESS_KEY_OPTION];
        $url = sprintf(self::LATEST_URL, $currencyPair->getBaseCurrency(), $accessKey);
        return $this->createRate($url, $currencyPair);
    }

    public function processOptions(array &$options)
    {
        if (!isset($options[self::ACCESS_KEY_OPTION])) {
            throw new \InvalidArgumentException('The "access_key" option must be provided to use fixer.io');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $accessKey = $this->options[self::ACCESS_KEY_OPTION];
        $url = sprintf(
            self::HISTORICAL_URL,
            $exchangeQuery->getDate()->format('Y-m-d'),
            $currencyPair->getBaseCurrency(),
            $accessKey
        );

        return $this->createRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        return true;
    }

    /**
     * Creates a rate.
     *
     * @param string       $url
     * @param CurrencyPair $currencyPair
     *
     * @return ExchangeRate|null
     *
     * @throws Exception
     */
    private function createRate($url, CurrencyPair $currencyPair)
    {
        $content = $this->request($url);
        $data = StringUtil::jsonToArray($content);

        if (isset($data['error'])) {
            throw new Exception($data['error']);
        }

        if (isset($data['rates'][$currencyPair->getQuoteCurrency()])) {
            $date = new \DateTime($data['date']);
            $rate = $data['rates'][$currencyPair->getQuoteCurrency()];

            return new ExchangeRate($rate, $date);
        }

        return null;
    }
}
