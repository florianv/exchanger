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

use Exchanger\Contract\ExchangeRate as ExchangeRateContract;
use Exchanger\Contract\ExchangeRateQuery as ExchangeRateQueryContract;
use Exchanger\Contract\HistoricalExchangeRateQuery as HistoricalExchangeRateQueryContract;

/**
 * Trait to implement to add historical service support.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
trait SupportsHistoricalQueries
{
    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQueryContract $exchangeQuery): ExchangeRateContract
    {
        if ($exchangeQuery instanceof HistoricalExchangeRateQueryContract) {
            return $this->getHistoricalExchangeRate($exchangeQuery);
        }

        return $this->getLatestExchangeRate($exchangeQuery);
    }

    /**
     * Gets the latest rate.
     *
     * @param ExchangeRateQueryContract $exchangeQuery
     *
     * @return ExchangeRateContract
     */
    abstract protected function getLatestExchangeRate(ExchangeRateQueryContract $exchangeQuery): ExchangeRateContract;

    /**
     * Gets an historical rate.
     *
     * @param HistoricalExchangeRateQueryContract $exchangeQuery
     *
     * @return ExchangeRateContract
     */
    abstract protected function getHistoricalExchangeRate(HistoricalExchangeRateQueryContract $exchangeQuery): ExchangeRateContract;
}
