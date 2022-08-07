<?php

namespace Exchanger\Service;

use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;

/**
 * National Bank of Georgia Service.
 *
 * @author Uğur Özkan
 */
final class NationalBankOfGeorgia extends HttpService
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
