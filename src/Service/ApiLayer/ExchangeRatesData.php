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

namespace Exchanger\Service\ApiLayer;

use Exchanger\Contract\CurrencyPair;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\Exception\NonBreakingInvalidArgumentException;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\Service\HttpService;
use Exchanger\Service\SupportsHistoricalQueries;
use Exchanger\StringUtil;

/**
 * ApiLayer Exchange Rates Data API.
 *
 * @see https://exchangeratesapi.io
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class ExchangeRatesData extends HttpService
{
    use SupportsHistoricalQueries;

    const API_KEY_OPTION = 'api_key';

    const LATEST_URL = 'https://api.apilayer.com/exchangerates_data/latest?base=%s&apikey=%s&symbols=%s';

    const HISTORICAL_URL = 'https://api.apilayer.com/exchangerates_data/%s?base=%s&apikey=%s&symbols=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options[self::API_KEY_OPTION])) {
            throw new NonBreakingInvalidArgumentException('The "api_key" option must be provided to use Exchange Rates Data (https://exchangeratesapi.io).');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $url = sprintf(
            self::LATEST_URL,
            $currencyPair->getBaseCurrency(),
            $this->options[self::API_KEY_OPTION],
            $currencyPair->getQuoteCurrency()
        );

        return $this->doCreateRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $url = sprintf(
            self::HISTORICAL_URL,
            $exchangeQuery->getDate()->format('Y-m-d'),
            $exchangeQuery->getCurrencyPair()->getBaseCurrency(),
            $this->options[self::API_KEY_OPTION],
            $currencyPair->getQuoteCurrency()
        );

        return $this->doCreateRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return true;
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
        return 'apilayer_exchange_rates_data';
    }
}
