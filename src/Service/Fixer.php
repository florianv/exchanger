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

    const ENTERPRISE_LATEST_URL = 'http://data.fixer.io/api/latest?base=%s&access_key=%s';

    const ENTERPRISE_HISTORICAL_URL = 'http://data.fixer.io/api/%s?base=%s&access_key=%s';

    const FREE_LATEST_URL = 'http://data.fixer.io/api/latest?access_key=%s';

    const FREE_HISTORICAL_URL = 'http://data.fixer.io/api/%s?access_key=%s';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if ($this->options['enterprise']) {
            $url = sprintf(
                self::ENTERPRISE_LATEST_URL,
                $currencyPair->getBaseCurrency(),
                $this->options[self::ACCESS_KEY_OPTION]
            );
        } else {
            $url = sprintf(
                self::FREE_LATEST_URL,
                $this->options[self::ACCESS_KEY_OPTION]
            );
        }

        return $this->createRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options)
    {
        if (!isset($options[self::ACCESS_KEY_OPTION])) {
            throw new \InvalidArgumentException('The "access_key" option must be provided to use fixer.io');
        }

        if (!isset($options['enterprise'])) {
            $options['enterprise'] = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if ($this->options['enterprise']) {
            $url = sprintf(
                self::ENTERPRISE_HISTORICAL_URL,
                $exchangeQuery->getDate()->format('Y-m-d'),
                $exchangeQuery->getCurrencyPair()->getBaseCurrency(),
                $this->options[self::ACCESS_KEY_OPTION]
            );
        } else {
            $url = sprintf(
                self::FREE_HISTORICAL_URL,
                $exchangeQuery->getDate()->format('Y-m-d'),
                $this->options[self::ACCESS_KEY_OPTION]
            );
        }

        return $this->createRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        return $this->options['enterprise'] || 'EUR' === $exchangeQuery->getCurrencyPair()->getBaseCurrency();
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
            throw new Exception($this->getErrorMessage($data['error']['code']));
        }

        if (isset($data['rates'][$currencyPair->getQuoteCurrency()])) {
            $date = new \DateTime($data['date']);
            $rate = $data['rates'][$currencyPair->getQuoteCurrency()];

            return new ExchangeRate($rate, $date);
        }

        return null;
    }

    /**
     * Gets the error message corresponding to the error code.
     *
     * @param string $code The error code
     *
     * @return string
     */
    private function getErrorMessage($code)
    {
        $errors = [
            404 => 'The requested resource does not exist.',
            101 => 'No API Key was specified or an invalid API Key was specified.',
            103 => 'The requested API endpoint does not exist.',
            104 => 'The maximum allowed API amount of monthly API requests has been reached.',
            105 => 'The current subscription plan does not support this API endpoint.',
            106 => 'The current request did not return any results.',
            102 => 'The account this API request is coming from is inactive.',
            201 => 'An invalid base currency has been entered.',
            202 => 'One or more invalid symbols have been specified.',
            301 => 'No date has been specified.',
            302 => 'An invalid date has been specified.',
            403 => 'No or an invalid amount has been specified.',
            501 => 'No or an invalid timeframe has been specified.',
            502 => 'No or an invalid "start_date" has been specified.',
            503 => 'No or an invalid "end_date" has been specified.',
            504 => 'An invalid timeframe has been specified.',
            505 => 'The specified timeframe is too long, exceeding 365 days.',
        ];

        return isset($errors[$code]) ? $errors[$code] : '';
    }
}
