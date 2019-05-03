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
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;

/**
 * ExchangeRatesApi Service.
 *
 * @author Arjan Westdorp <arjanwestdorp@gmail.com>
 */
final class ExchangeRatesApi extends HttpService
{
    use SupportsHistoricalQueries;

    const LATEST_URL = 'https://api.exchangeratesapi.io/latest?base=%s';

    const HISTORICAL_URL = 'https://api.exchangeratesapi.io/%s?base=%s';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $url = sprintf(
            self::LATEST_URL,
            $currencyPair->getBaseCurrency()
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
            $exchangeQuery->getCurrencyPair()->getBaseCurrency()
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
     * @param string       $url
     * @param CurrencyPair $currencyPair
     *
     * @return ExchangeRate
     *
     * @throws Exception
     */
    private function doCreateRate($url, CurrencyPair $currencyPair): ExchangeRate
    {
        $content = $this->request($url);
        $data = StringUtil::jsonToArray($content);

        if (isset($data['error'])) {
            throw new Exception($data['error']);
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
