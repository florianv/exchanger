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
 * UniRateAPI Service.
 *
 * @see https://unirateapi.com
 */
final class UniRateApi extends HttpService
{
    use SupportsHistoricalQueries;

    public const API_KEY_OPTION = 'api_key';

    public const LATEST_URL = 'https://api.unirateapi.com/api/rates?api_key=%s&from=%s&to=%s';

    public const HISTORICAL_URL = 'https://api.unirateapi.com/api/historical/rates?api_key=%s&date=%s&from=%s&to=%s';

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
        return true;
    }

    /** {@inheritdoc} */
    #[\Override]
    public function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $url = sprintf(
            self::LATEST_URL,
            urlencode((string) $this->options[self::API_KEY_OPTION]),
            $currencyPair->getBaseCurrency(),
            $currencyPair->getQuoteCurrency(),
        );

        return $this->doCreateRate($url, $currencyPair, new \DateTime());
    }

    /** {@inheritdoc} */
    #[\Override]
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $url = sprintf(
            self::HISTORICAL_URL,
            urlencode((string) $this->options[self::API_KEY_OPTION]),
            $exchangeQuery->getDate()->format('Y-m-d'),
            $currencyPair->getBaseCurrency(),
            $currencyPair->getQuoteCurrency(),
        );

        return $this->doCreateRate($url, $currencyPair, $exchangeQuery->getDate());
    }

    /**
     * @throws Exception
     */
    private function doCreateRate(string $url, CurrencyPair $currencyPair, \DateTimeInterface $date): ExchangeRate
    {
        $response = $this->getResponse($url, ['Accept' => 'application/json']);

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

        if (!isset($data['rate']) || (!is_string($data['rate']) && !is_numeric($data['rate']))) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        return $this->createRate($currencyPair, (float) $data['rate'], $date);
    }

    /** {@inheritdoc} */
    #[\Override]
    public function getName(): string
    {
        return 'unirate_api';
    }
}
