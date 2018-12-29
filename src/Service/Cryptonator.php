<?php

namespace Exchanger\Service;

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

class Cryptonator extends Service
{
    const LATEST_URL = 'https://api.cryptonator.com/api/ticker/%s-%s';

    /**
     * Gets the exchange rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRateContract
     *
     * @throws Exception
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $response = $this->request(
            sprintf(
                self::LATEST_URL,
                strtolower($currencyPair->getBaseCurrency()),
                strtolower($currencyPair->getQuoteCurrency())
            )
        );

        $data = StringUtil::jsonToArray($response);

        if (!$data['success']) {
            $message = !empty($data['error']) ? $data['error'] : 'Unknown error';

            throw new Exception($message);
        }

        $date = (new \DateTime())->setTimestamp($data['timestamp']);

        return new ExchangeRate($data['ticker']['price'], $date);
    }

    /**
     * Tells if the service supports the exchange rate query.
     *
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return bool
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return !$exchangeQuery instanceof HistoricalExchangeRateQuery
            && in_array($exchangeQuery->getCurrencyPair()->getBaseCurrency(), $this->getSupportedCodes())
            && in_array($exchangeQuery->getCurrencyPair()->getQuoteCurrency(), $this->getSupportedCodes());
    }

    /**
     * Array of codes supported according to.
     *
     * @url https://www.cryptonator.com/api/currencies
     *
     * @return array
     */
    private function getSupportedCodes(): array
    {
        return require __DIR__.'/resources/cryptonator-codes.php';
    }
}
