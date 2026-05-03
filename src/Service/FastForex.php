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
use Exchanger\CurrencyPair;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * fastFOREX.io Service.
 *
 * @see https://www.fastforex.io
 *
 * @author Tom <tom@whamsoftware.com>
 */
final class FastForex extends HttpService
{
    use SupportsHistoricalQueries;

    const API_KEY_OPTION = 'api_key';
    const API_KEY_HEADER = 'X-Api-Key';

    const FETCH_ONE_URL = 'https://api.fastforex.io/fetch-one?from=%s&to=%s';

    const HISTORICAL_URL = 'https://api.fastforex.io/historical?date=%s&from=%s&to=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options[self::API_KEY_OPTION])) {
            throw new \InvalidArgumentException('The "api_key" option must be provided.');
        }
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
    public function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $response = $this->getResponse(
            sprintf(
                self::FETCH_ONE_URL,
                $currencyPair->getBaseCurrency(),
                $currencyPair->getQuoteCurrency()
            ),
            [
                self::API_KEY_HEADER => $this->options[self::API_KEY_OPTION],
            ]
        );

        return $this->processResponse($response, $currencyPair, 'result');
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $response = $this->getResponse(
            sprintf(
                self::HISTORICAL_URL,
                $exchangeQuery->getDate()->format('Y-m-d'),
                $currencyPair->getBaseCurrency(),
                $currencyPair->getQuoteCurrency()
            ),
            [
                self::API_KEY_HEADER => $this->options[self::API_KEY_OPTION],
            ]
        );

        return $this->processResponse($response, $currencyPair, 'results');
    }

    protected function processResponse(
        \Psr\Http\Message\ResponseInterface $response,
        CurrencyPair $currencyPair,
        string $resultKey
    ): ExchangeRate {
        try {
            $result = StringUtil::jsonToArray(
                $response->getBody()->__toString()
            );
        } catch (\Throwable $thrown) {
            $result = ['error' => 'Failed to parse response'];
        }

        if ($response->getStatusCode() !== 200 || isset($result['error'])) {
            throw new \Exchanger\Exception\Exception(
                empty($result['error'])
                    ? sprintf('Failed with HTTP response code %d', $response->getStatusCode())
                    : $result['error']
            );
        }

        try {
            $date = new \DateTime($result['updated'] ?? ($result['date'] ?? 'now'));
        } catch (\Throwable $thrown) {
            $date = new \DateTime();
        }

        if (isset($result['base']) && $result['base'] == $currencyPair->getBaseCurrency()) {
            $quoteCurrency = $currencyPair->getQuoteCurrency();
            if (isset($result[$resultKey][$quoteCurrency])) {
                return $this->createRate($currencyPair, (float) ($result[$resultKey][$quoteCurrency]), $date);
            }
        }

        throw new \Exchanger\Exception\Exception('Unknown error');
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'fastforex';
    }
}
