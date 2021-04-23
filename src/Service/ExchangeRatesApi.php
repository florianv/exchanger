<?php

declare(strict_types=1);

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
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\Exception\NonBreakingInvalidArgumentException;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;

/**
 * ExchangeRatesApi Service.
 *
 * @see https://exchangeratesapi.io/documentation/
 *
 * @author Arjan Westdorp <arjanwestdorp@gmail.com>
 */
final class ExchangeRatesApi extends HttpService
{
    use SupportsHistoricalQueries;

    const LATEST_URL = 'https://api.exchangeratesapi.io/latest?base=%s&access_key=%s&symbols=%s';

    const HISTORICAL_URL = 'https://api.exchangeratesapi.io/%s?base=%s&access_key=%s&symbols=%s';

    const FREE_LATEST_URL = 'http://api.exchangeratesapi.io/latest?access_key=%s&symbols=%s';

    const FREE_HISTORICAL_URL = 'http://api.exchangeratesapi.io/%s?access_key=%s&symbols=%s';

    const ACCESS_KEY_OPTION = 'access_key';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options[self::ACCESS_KEY_OPTION])) {
            throw new NonBreakingInvalidArgumentException('The "access_key" option must be provided to use exchangeratesapi.io');
        }

        if (!isset($options['enterprise'])) {
            $options['enterprise'] = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        if ($this->options['enterprise']) {
            $url = sprintf(
                self::LATEST_URL,
                $currencyPair->getBaseCurrency(),
                $this->options[self::ACCESS_KEY_OPTION],
                $currencyPair->getQuoteCurrency()
            );
        } else {
            $url = sprintf(
                self::FREE_LATEST_URL,
                $this->options[self::ACCESS_KEY_OPTION],
                $currencyPair->getQuoteCurrency()
            );
        }

        return $this->doCreateRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        if ($this->options['enterprise']) {
            $url = sprintf(
                self::HISTORICAL_URL,
                $exchangeQuery->getDate()->format('Y-m-d'),
                $exchangeQuery->getCurrencyPair()->getBaseCurrency(),
                $this->options[self::ACCESS_KEY_OPTION],
                $currencyPair->getQuoteCurrency()
            );
        } else {
            $url = sprintf(
                self::FREE_HISTORICAL_URL,
                $exchangeQuery->getDate()->format('Y-m-d'),
                $this->options[self::ACCESS_KEY_OPTION],
                $exchangeQuery->getCurrencyPair()->getQuoteCurrency()
            );
        }

        return $this->doCreateRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return $this->options['enterprise'] || 'EUR' === $exchangeQuery->getCurrencyPair()->getBaseCurrency();
    }

    /**
     * Creates a rate.
     *
     * @param string $url
     *
     * @throws Exception
     */
    private function doCreateRate($url, CurrencyPair $currencyPair): ExchangeRate
    {
        $content = $this->request($url);
        $data = StringUtil::jsonToArray($content);

        if (isset($data['error'])) {
            if (isset($data['error']['code'])) {
                if (\in_array($data['error']['code'], [
                    'invalid_currency_codes',
                    'invalid_base_currency',
                    'no_rates_available',
                ], true)) {
                    throw new UnsupportedCurrencyPairException($currencyPair, $this);
                }
                if (isset($data['error']['message'])) {
                    throw new Exception($data['error']['message']);
                } else {
                    throw new Exception('Service return error code: '.$data['error']['code']);
                }
            } else {
                throw new Exception('Service return unhandled error');
            }
        }

        if (isset($data['rates'][$currencyPair->getQuoteCurrency()])) {
            $date = new \DateTime($data['date']);
            $rate = $data['rates'][$currencyPair->getQuoteCurrency()];

            return $this->createRate($currencyPair, (float) $rate, $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'exchange_rates_api';
    }
}
