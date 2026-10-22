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
use Exchanger\Exception\Exception;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

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
        if (!isset($options[self::API_KEY_OPTION])) {
            throw new \InvalidArgumentException('The "api_key" option must be provided.');
        }
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

        try {
            $data = StringUtil::jsonToArray($response->getBody()->__toString());
        } catch (\Throwable $thrown) {
            $data = ['error' => 'Failed to parse response'];
        }

        if ($response->getStatusCode() !== 200 || isset($data['error'])) {
            $message = isset($data['error']) && is_string($data['error'])
                ? $data['error']
                : sprintf('Failed with HTTP response code %d', $response->getStatusCode());

            throw new Exception($message);
        }

        foreach ($data as $row) {
            if (!is_array($row) || !isset($row['target'], $row['rate'])) {
                continue;
            }

            if ($row['target'] !== $currencyPair->getQuoteCurrency() || !is_numeric($row['rate'])) {
                continue;
            }

            $date = new \DateTime();

            if (isset($row['time']) && is_string($row['time'])) {
                try {
                    $date = new \DateTime($row['time']);
                } catch (\Exception $exception) {
                    // Keep the current date when the time cannot be parsed.
                }
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
