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
    private const BASE_URL = "https://api.frankfurter.dev/v2/rates";

    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return true;
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

        $url = self::BASE_URL . "?base={$base}&quotes={$quote}";

        if ($exchangeQuery instanceof HistoricalExchangeRateQueryContract) {
            $url .= '&date=' . $exchangeQuery->getDate()->format('Y-m-d');
        }

        $content = $this->request($url);
        $data = json_decode($content, true);

        if (!isset($data[0]['rate'])) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rate = (float)$data[0]['rate'];
        $date = new \DateTime($data[0]['date']);

        return $this->createRate($currencyPair, $rate, $date);
    }
}
