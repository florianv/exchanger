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
        $url = sprintf(
            self::URL,
            urlencode($currencyPair->getBaseCurrency()),
            urlencode($currencyPair->getQuoteCurrency()),
        );

        $response = $this->getResponse($url, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . (string) $this->options[self::API_KEY_OPTION],
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->__toString();

        // The API answers an invalid or unsupported currency code with HTTP 400.
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

        $quoteCurrency = strtoupper($currencyPair->getQuoteCurrency());

        foreach ($data as $row) {
            if (!is_array($row) || !isset($row['target'], $row['rate'])) {
                continue;
            }

            if (!is_string($row['target']) || strtoupper($row['target']) !== $quoteCurrency) {
                continue;
            }

            if (!is_numeric($row['rate']) || (float) $row['rate'] <= 0) {
                continue;
            }

            try {
                $date = isset($row['time']) && is_string($row['time']) ? new \DateTime($row['time']) : new \DateTime();
            } catch (\Throwable $thrown) {
                $date = new \DateTime();
            }

            return $this->createRate($currencyPair, (float) $row['rate'], $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /** {@inheritdoc} */
    #[\Override]
    public function getName(): string
    {
        return 'allratestoday';
    }
}
