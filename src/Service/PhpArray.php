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

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Service that retrieves rates from an array.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class PhpArray extends Service
{
    /**
     * The latest rates.
     *
     * @var ExchangeRate[]|string[]
     */
    private $latestRates;

    /**
     * The historical rates.
     *
     * @var ExchangeRate[][]|string[][]
     */
    private $historicalRates;

    /**
     * Constructor.
     *
     * @param ExchangeRate[]|string[]     $latestRates     An array of rates indexed by the corresponding currency pair symbol
     * @param ExchangeRate[][]|string[][] $historicalRates An array of rates indexed by the date in Y-m-d format
     */
    public function __construct(array $latestRates, array $historicalRates = [])
    {
        $this->validateRates($latestRates);
        $this->validateHistoricalRates($historicalRates);

        $this->latestRates = $latestRates;
        $this->historicalRates = $historicalRates;
    }

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if (!$this->supportQuery($exchangeQuery)) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        if ($exchangeQuery instanceof HistoricalExchangeRateQuery) {
            return $this->getHistoricalExchangeRate($exchangeQuery);
        }

        return $this->getLatestExchangeRate($exchangeQuery);
    }

    /**
     * Gets the latest rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate
     */
    private function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $rate = (float) $this->latestRates[(string) $currencyPair];

        return $this->createInstantRate($currencyPair, $rate);
    }

    /**
     * Gets an historical rate.
     *
     * @param HistoricalExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate
     */
    private function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        $date = $exchangeQuery->getDate();
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $rate = (float) $this->historicalRates[$date->format('Y-m-d')][(string) $currencyPair];

        return $this->createRate($currencyPair, $rate, $date);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if ($exchangeQuery instanceof HistoricalExchangeRateQuery) {
            $date = $exchangeQuery->getDate();

            return isset($this->historicalRates[$date->format('Y-m-d')][(string) $currencyPair]);
        }

        return isset($this->latestRates[(string) $currencyPair]);
    }

    /**
     * Validate the rates array.
     *
     * @param array $rates
     *
     * @throws \InvalidArgumentException
     */
    private function validateRates(array $rates)
    {
        foreach ($rates as $rate) {
            if (!is_scalar($rate)) {
                throw new \InvalidArgumentException(sprintf('Rates passed to the PhpArray service must be scalars, "%s" given.', \gettype($rate)));
            }
        }
    }

    /**
     * Validate the historical rates array.
     *
     * @param array $rates
     *
     * @throws \InvalidArgumentException
     */
    private function validateHistoricalRates(array $rates)
    {
        foreach ($rates as $rate) {
            $this->validateRates($rate);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'array';
    }
}
