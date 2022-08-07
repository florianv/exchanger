<?php

namespace Exchanger\Service;

use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;

/**
 * The Central Bank of the Republic of Uzbekistan Service.
 *
 * @author Uğur Özkan
 */
final class CentralBankOfRepublicUzbekistan extends HttpService
{
    use SupportsHistoricalQueries;

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
    }

    /**
     * @inheritDoc
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
    }

    /**
     * @inheritDoc
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
    }

    /**
     * @inheritDoc
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRate
    {
    }
}
