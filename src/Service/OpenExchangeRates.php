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
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Open Exchange Rates Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class OpenExchangeRates extends HttpService
{
    use SupportsHistoricalQueries;

    const FREE_LATEST_URL = 'https://openexchangerates.org/api/latest.json?app_id=%s&show_alternative=1';

    const ENTERPRISE_LATEST_URL = 'https://openexchangerates.org/api/latest.json?app_id=%s&base=%s&symbols=%s&show_alternative=1';

    const FREE_HISTORICAL_URL = 'https://openexchangerates.org/api/historical/%s.json?app_id=%s&show_alternative=1';

    const ENTERPRISE_HISTORICAL_URL = 'https://openexchangerates.org/api/historical/%s.json?app_id=%s&base=%s&symbols=%s&show_alternative=1';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options['app_id'])) {
            throw new \InvalidArgumentException('The "app_id" option must be provided.');
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
                self::ENTERPRISE_LATEST_URL,
                $this->options['app_id'],
                $currencyPair->getBaseCurrency(),
                $currencyPair->getQuoteCurrency()
            );
        } else {
            $url = sprintf(self::FREE_LATEST_URL, $this->options['app_id']);
        }

        return $this->doCreateRate($url, $exchangeQuery);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if ($this->options['enterprise']) {
            $url = sprintf(
                self::ENTERPRISE_HISTORICAL_URL,
                $exchangeQuery->getDate()->format('Y-m-d'),
                $this->options['app_id'],
                $currencyPair->getBaseCurrency(),
                $currencyPair->getQuoteCurrency()
            );
        } else {
            $url = sprintf(
                self::FREE_HISTORICAL_URL,
                $exchangeQuery->getDate()->format('Y-m-d'),
                $this->options['app_id']
            );
        }

        return $this->doCreateRate($url, $exchangeQuery);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return $this->options['enterprise'] || 'USD' === $exchangeQuery->getCurrencyPair()->getBaseCurrency();
    }

    /**
     * Creates a rate.
     *
     * @param string            $url
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate
     *
     * @throws Exception
     */
    private function doCreateRate(string $url, ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        $content = $this->request($url);
        $data = StringUtil::jsonToArray($content);

        if (isset($data['error'])) {
            throw new Exception($data['description']);
        }

        $date = new \DateTime();
        $date->setTimestamp($data['timestamp']);
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if ($data['base'] === $currencyPair->getBaseCurrency()
            && isset($data['rates'][$currencyPair->getQuoteCurrency()])
        ) {
            return $this->createRate($currencyPair, (float) ($data['rates'][$currencyPair->getQuoteCurrency()]), $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'open_exchange_rates';
    }
}
