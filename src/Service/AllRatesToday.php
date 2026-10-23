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
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedExchangeQueryException;
use Exchanger\StringUtil;

/**
 * AllRatesToday Service.
 *
 * Supports latest rates only. The API key is sent as a Bearer token.
 *
 * @see https://allratestoday.com
 */
final class AllRatesToday extends HttpService
{
    public const API_KEY_OPTION = 'api_key';

    public const URL = 'https://allratestoday.com/api/v1/rates?source=%s&target=%s';

    /** {@inheritdoc} */
    #[\Override]
    public function processOptions(array &$options): void
    {
        if (!isset($options[self::API_KEY_OPTION]) || !is_string($options[self::API_KEY_OPTION]) || trim($options[self::API_KEY_OPTION]) === '') {
            throw new \InvalidArgumentException('The "api_key" option must be provided.');
        }

        $options[self::API_KEY_OPTION] = trim($options[self::API_KEY_OPTION]);
    }

    /** {@inheritdoc} */
    #[\Override]
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return !$exchangeQuery instanceof HistoricalExchangeRateQuery;
    }

    /** {@inheritdoc} */
    #[\Override]
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        if (!$this->supportQuery($exchangeQuery)) {
            throw new UnsupportedExchangeQueryException($exchangeQuery, $this);
        }

        $currencyPair = $exchangeQuery->getCurrencyPair();
        $baseCurrency = strtoupper($currencyPair->getBaseCurrency());
        $quoteCurrency = strtoupper($currencyPair->getQuoteCurrency());
        $url = sprintf(self::URL, urlencode($baseCurrency), urlencode($quoteCurrency));

        $response = $this->getResponse($url, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . (string) $this->options[self::API_KEY_OPTION],
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->__toString();

        // The request only sends the two currency codes, so of the documented causes of a 400
        // only an invalid currency code applies.
        if ($statusCode === 400) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        try {
            $data = StringUtil::jsonToArray($body);
        } catch (\Throwable $thrown) {
            throw new Exception(sprintf('Failed to parse response (HTTP response code %d)', $statusCode), 0, $thrown);
        }

        if ($statusCode !== 200 || isset($data['error'])) {
            $error = isset($data['error']) && is_string($data['error']) && $data['error'] !== '' ? $data['error'] : null;

            if ($error === null) {
                $message = sprintf('Failed with HTTP response code %d', $statusCode);
            } elseif ($statusCode !== 200) {
                $message = sprintf('%s (HTTP response code %d)', $error, $statusCode);
            } else {
                $message = $error;
            }

            throw new Exception($message);
        }

        // The API always answers with a list of rows, one per target currency.
        if (!array_is_list($data)) {
            throw new Exception('Unexpected response: expected a list of rates');
        }

        foreach ($data as $row) {
            if (!is_array($row) || !isset($row['source'], $row['target']) || !is_string($row['source']) || !is_string($row['target'])) {
                throw new Exception('Unexpected response: a rate is missing its currency codes');
            }

            if (strtoupper($row['source']) !== $baseCurrency || strtoupper($row['target']) !== $quoteCurrency) {
                continue;
            }

            if (!isset($row['rate']) || !is_numeric($row['rate']) || (float) $row['rate'] <= 0) {
                throw new Exception(sprintf('Unexpected response: invalid rate for %s/%s', $baseCurrency, $quoteCurrency));
            }

            return $this->createRate($currencyPair, (float) $row['rate'], $this->parseTime($row['time'] ?? null));
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * Parses the time the rate was observed, which the API sends in ISO 8601 (UTC).
     *
     * @throws Exception when the time is missing or not a date
     */
    private function parseTime(mixed $time): \DateTimeImmutable
    {
        if (!is_string($time) || $time === '') {
            throw new Exception('Unexpected response: the rate has no time');
        }

        try {
            return new \DateTimeImmutable($time);
        } catch (\Exception $thrown) {
            throw new Exception(sprintf('Unexpected response: invalid time "%s"', $time), 0, $thrown);
        }
    }

    /** {@inheritdoc} */
    #[\Override]
    public function getName(): string
    {
        return 'all_rates_today';
    }
}
