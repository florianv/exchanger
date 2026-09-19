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
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\StringUtil;

/**
 * Uses the currency "API" published on JSDelivr. See https://github.com/fawazahmed0/exchange-api
 *
 * @author Jan Böhmer
 */
final class FawazahmedCurrencyApi extends HttpService
{
    use SupportsHistoricalQueries;

    public const URL_TEMPLATE = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@%s/v1/currencies/%s.min.json';

    // 2024-03-02 is the earliest release currently published on the CDN; revisit if older history gets added.
    private const EARLIEST_SUPPORTED_DATE = '2024-03-02';

    #[\Override]
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        return $this->fetchRate($exchangeQuery, 'latest');
    }

    #[\Override]
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        return $this->fetchRate($exchangeQuery, $exchangeQuery->getDate()->format('Y-m-d'));
    }

    private function fetchRate(ExchangeRateQuery $exchangeQuery, string $release): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $base = strtolower($currencyPair->getBaseCurrency());
        $quote = strtolower($currencyPair->getQuoteCurrency());

        $url = sprintf(self::URL_TEMPLATE, $release, $base);
        $content = $this->request($url);

        try {
            $data = StringUtil::jsonToArray($content);
        } catch (\RuntimeException $e) {
            if ($exchangeQuery instanceof HistoricalExchangeRateQuery) {
                throw new UnsupportedDateException($exchangeQuery->getDate(), $this);
            }

            throw $e;
        }

        if (!isset($data[$base][$quote])) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rate = (float) $data[$base][$quote];
        $date = new \DateTime($data['date']);

        return $this->createRate($currencyPair, $rate, $date);
    }

    #[\Override]
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return !($exchangeQuery instanceof HistoricalExchangeRateQuery && $exchangeQuery->getDate() < new \DateTime(self::EARLIEST_SUPPORTED_DATE));
    }

    #[\Override]
    public function getName(): string
    {
        return 'fawazahmed_currency_api';
    }
}
