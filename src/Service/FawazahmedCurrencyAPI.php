<?php

declare(strict_types=1);


namespace Exchanger\Service;

use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;

/**
 * Uses the currency "API" published on JSDelivr. See https://github.com/fawazahmed0/exchange-api
 *
 * @author Jan Böhmer
 */
final class FawazahmedCurrencyAPI extends HttpService
{
    public const URL_TEMPLATE = "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@%s/v1/currencies/%s.min.json";

    public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $base = strtolower($currencyPair->getBaseCurrency());
        $quote = strtolower($currencyPair->getQuoteCurrency());

        if ($exchangeQuery instanceof HistoricalExchangeRateQuery) {
            $date = $exchangeQuery->getDate()->format('Y-m-d');
            $url = sprintf(self::URL_TEMPLATE, $date, $base);
        } else {
            $url = sprintf(self::URL_TEMPLATE,'latest', $base);
        }

        $content = $this->request($url);
        $data = json_decode($content, true);

        if (!isset($data[$base]) || !isset($data[$base][$quote])) {
            throw new \Exchanger\Exception\UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rate = (float)$data[$base][$quote];
        $date = new \DateTime($data['date']);

        return $this->createRate($currencyPair, $rate, $date);
    }

    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return !($exchangeQuery instanceof HistoricalExchangeRateQuery && $exchangeQuery->getDate() < new \DateTime('2025-01-01'));
    }

    public function getName(): string
    {
        return "fawazahmed_currency_api";
    }
}
