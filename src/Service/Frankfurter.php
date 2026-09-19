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
use Exchanger\Contract\HistoricalExchangeRateQuery as HistoricalExchangeRateQueryContract;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Uses the free API at https://frankfurter.dev/
 */
final class Frankfurter extends HttpService
{
    use SupportsHistoricalQueries;

    private const BASE_URL = 'https://api.frankfurter.dev/v2/rates';

    /**
     * Frankfurter is based on the ECB reference rates, which start on this date.
     */
    private const START_DATE = '1999-01-04';

    #[\Override]
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        if ($exchangeQuery instanceof HistoricalExchangeRateQueryContract) {
            return $exchangeQuery->getDate() >= new \DateTime(self::START_DATE);
        }

        return true;
    }

    #[\Override]
    public function getName(): string
    {
        return 'frankfurter';
    }

    /** {@inheritdoc} */
    #[\Override]
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        return $this->fetchRate($exchangeQuery);
    }

    /** {@inheritdoc} */
    #[\Override]
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQueryContract $exchangeQuery): ExchangeRateContract
    {
        return $this->fetchRate($exchangeQuery);
    }

    private function fetchRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $base = $currencyPair->getBaseCurrency();
        $quote = $currencyPair->getQuoteCurrency();

        $url = self::BASE_URL . "?base={$base}&quotes={$quote}";

        if ($exchangeQuery instanceof HistoricalExchangeRateQueryContract) {
            $url .= '&date=' . $exchangeQuery->getDate()->format('Y-m-d');
        }

        $content = $this->request($url);
        $data = StringUtil::jsonToArray($content);

        if (!isset($data[0]['rate'])) {
            if ($exchangeQuery instanceof HistoricalExchangeRateQueryContract) {
                throw new UnsupportedDateException($exchangeQuery->getDate(), $this);
            }

            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rate = (float) $data[0]['rate'];
        $date = new \DateTime((string) $data[0]['date']);

        return $this->createRate($currencyPair, $rate, $date);
    }
}
