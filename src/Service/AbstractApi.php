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
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Abstract API Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class AbstractApi extends HttpService
{
    use SupportsHistoricalQueries;

    const API_KEY_OPTION = 'api_key';

    const LATEST_URL = 'https://exchange-rates.abstractapi.com/v1/live?api_key=%s&base=%s';

    const HISTORICAL_URL = 'https://exchange-rates.abstractapi.com/v1/historical?api_key=%s&base=%s&date=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options[self::API_KEY_OPTION])) {
            throw new \InvalidArgumentException('The "api_key" option must be provided to use abstractapi.com');
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
            $this->options[self::API_KEY_OPTION],
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
            $this->options[self::API_KEY_OPTION],
            $currencyPair->getBaseCurrency(),
            $exchangeQuery->getDate()->format('Y-m-d')
        );

        return $this->doCreateRate($url, $currencyPair);
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

        if (isset($data['exchange_rates'][$currencyPair->getQuoteCurrency()])) {
            if (isset($data['date'])) {
                $date = \DateTime::createFromFormat(
                    'Y-m-d',
                    $data['date'],
                    new \DateTimeZone('UTC')
                );
            } else {
                $date = new \DateTime();
                $date->setTimezone(new \DateTimeZone('UTC'));
                $date->setTimestamp($data['last_updated']);
            }

            $rate = $data['exchange_rates'][$currencyPair->getQuoteCurrency()];

            return $this->createRate($currencyPair, (float) $rate, $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'abstract_api';
    }
}
