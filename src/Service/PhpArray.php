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

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\ExchangeRateService;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\InternalException;
use Exchanger\ExchangeRate;

/**
 * Service that retrieves rates from an array.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class PhpArray implements ExchangeRateService
{
    /**
     * The rates.
     *
     * @var ExchangeRate[]
     */
    private $rates;

    /**
     * Constructor.
     *
     * @param ExchangeRate[]|string[] $rates An array of rates indexed by the corresponding currency pair symbol
     */
    public function __construct(array $rates)
    {
        $this->rates = $rates;
    }

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $rate = $this->rates[$currencyPair->__toString()];

        if (is_scalar($rate)) {
            $rate = new ExchangeRate($rate);
        } elseif (!$rate instanceof ExchangeRate) {
            throw new InternalException(sprintf(
                'Rates passed to the PhpArray service must be Rate instances or scalars "%s" given.',
                gettype($rate)
            ));
        }

        return $rate;
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        return !$exchangeQuery instanceof HistoricalExchangeRateQuery
        && isset($this->rates[$currencyPair->__toString()]);
    }
}
