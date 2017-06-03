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

use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;

/**
 * Base class for historical http based services.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
abstract class HistoricalService extends Service
{
    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if ($exchangeQuery instanceof HistoricalExchangeRateQuery) {
            if ($rate = $this->getHistoricalExchangeRate($exchangeQuery)) {
                return $rate;
            }
        } elseif ($rate = $this->getLatestExchangeRate($exchangeQuery)) {
            return $rate;
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * Gets the latest rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate|null
     */
    abstract protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery);

    /**
     * Gets an historical rate.
     *
     * @param HistoricalExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate|null
     */
    abstract protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery);
}
