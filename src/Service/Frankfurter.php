<?php

declare(strict_types=1);


namespace Exchanger\Service;

use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery as HistoricalExchangeRateQueryContract;
use Exchanger\Exception\UnsupportedCurrencyPairException;

/**
 * Uses the free API at https://frankfurter.dev/
 */
final class Frankfurter extends HttpService
{

    private const BASE_URL = "https://api.frankfurter.dev/v1/";
    private const SUPPORTED_CURRENCIES = [
        'AUD', 'BGN', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR',
        'GBP', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JPY',
        'KRW', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON',
        'SEK', 'SGD', 'THB', 'TRY', 'USD', 'ZAR'
    ];

    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        return in_array($currencyPair->getBaseCurrency(), self::SUPPORTED_CURRENCIES) &&
               in_array($currencyPair->getQuoteCurrency(), self::SUPPORTED_CURRENCIES);
    }

    public function getName(): string
    {
        return 'frankfurter';
    }

    public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $base = $currencyPair->getBaseCurrency();
        $quote = $currencyPair->getQuoteCurrency();

        if ($exchangeQuery instanceof HistoricalExchangeRateQueryContract) {
            $date = $exchangeQuery->getDate()->format('Y-m-d');
            $url = self::BASE_URL . "{$date}?base={$base}&symbols={$quote}";
        } else {
            $url = self::BASE_URL . "latest?base={$base}&symbols={$quote}";
        }

        $content = $this->request($url);
        $data = json_decode($content, true);

        if (!isset($data['rates'][$quote])) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rate = (float)$data['rates'][$quote];
        $date = new \DateTime($data['date']);

        return $this->createRate($currencyPair, $rate, $date);
    }
}
