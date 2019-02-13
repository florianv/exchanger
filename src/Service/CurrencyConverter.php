<?php

namespace Exchanger\Service;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exchanger\Contract\CurrencyPair;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;

/**
 * Currency Converter API service.
 *
 * @see https://github.com/florianv/laravel-swap/tree/master/doc#creating-a-service
 *
 * Docs for free plan:
 * @see https://free.currencyconverterapi.com/
 *
 * Docs for paid/enterprise plans:
 * @see https://www.currencyconverterapi.com/docs
 */
class CurrencyConverter extends HistoricalService
{
    const FREE_LATEST_URL = 'https://free.currencyconverterapi.com/api/v6/convert?q=%s&apiKey=%s';

    const ENTERPRISE_LATEST_URL = 'https://api.currencyconverterapi.com/api/v6/convert?q=%s&apiKey=%s';

    const FREE_HISTORICAL_URL = 'https://free.currencyconverterapi.com/api/v6/convert?q=%s&date=%s&apiKey=%s';

    const ENTERPRISE_HISTORICAL_URL = 'https://api.currencyconverterapi.com/api/v6/convert?q=%s&date=%s&apiKey=%s';

    /** {@inheritdoc} */
    public function processOptions(array &$options)
    {
        if (!isset($options['enterprise'])) {
            $options['enterprise'] = false;
        }

        if (!$options['enterprise'] && !isset($options['access_key'])) {
            throw new \InvalidArgumentException('The "access_key" option must be provided, please use https://free.currencyconverterapi.com/free-api-key to ask for a KEY.');
        }

        if ($options['enterprise'] && !isset($options['access_key'])) {
            throw new \InvalidArgumentException('The "access_key" option must be provided.');
        }
    }

    /** {@inheritdoc} */
    public function supportQuery(ExchangeRateQuery $exchangeRateQuery)
    {
        if ($this->isEnterprise()) {
            return true;
        }

        if (!$exchangeRateQuery instanceof HistoricalExchangeRateQuery) {
            return true;
        }

        if ($exchangeRateQuery->getDate() > new DateTime('now')) {
            return false;
        }

        return $exchangeRateQuery->getDate() > $this->getEarliestAvailableDateForHistoricalQuery();
    }

    /**
     * Gets the latest rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate
     *
     * @throws Exception
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $url = sprintf(
            $this->isEnterprise() ? self::ENTERPRISE_LATEST_URL : self::FREE_LATEST_URL,
            $this->stringifyCurrencyPair($exchangeQuery->getCurrencyPair()),
            $this->options['access_key']
        );

        return $this->fetchOnlineRate($url, $exchangeQuery);
    }

    /**
     * Gets an historical rate.
     *
     * @param HistoricalExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRate
     *
     * @throws Exception
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery)
    {
        $historicalDateTime = $this->getAdoptedDateTime($exchangeQuery->getDate());

        $url = sprintf(
            $this->isEnterprise() ? self::ENTERPRISE_HISTORICAL_URL : self::ENTERPRISE_HISTORICAL_URL,
            $this->stringifyCurrencyPair($exchangeQuery->getCurrencyPair()),
            $historicalDateTime->format('Y-m-d'),
            $this->options['access_key']
        );

        return $this->fetchOnlineRate($url, $exchangeQuery);
    }

    /**
     * Fetch online rate.
     *
     * @param string            $url
     * @param ExchangeRateQuery $exchangeRateQuery
     *
     * @return ExchangeRate
     *
     * @throws Exception
     */
    private function fetchOnlineRate($url, ExchangeRateQuery $exchangeRateQuery)
    {
        $currencyPair = $exchangeRateQuery->getCurrencyPair();

        $response = $this->getResponse($url);
        if (200 !== $response->getStatusCode()) {
            throw new Exception("Unexpected response status {$response->getReasonPhrase()}, $url");
        }

        $responsePayload = StringUtil::jsonToArray($response->getBody()->__toString());

        $keyAsCurrencyPair = $this->stringifyCurrencyPair($currencyPair);
        if (empty($responsePayload['results'][$keyAsCurrencyPair]['val'])) {
            throw new Exception("Unexpected response body {$response->getReasonPhrase()}");
        }

        if ($responsePayload['results'][$keyAsCurrencyPair]['fr'] !== $currencyPair->getBaseCurrency()) {
            throw new Exception("Unexpected base currency {$responsePayload['results'][$keyAsCurrencyPair]['fr']}");
        }

        if ($responsePayload['results'][$keyAsCurrencyPair]['to'] !== $currencyPair->getQuoteCurrency()) {
            throw new Exception("Unexpected quote currency {$responsePayload['results'][$keyAsCurrencyPair]['to']}");
        }

        if ($exchangeRateQuery instanceof HistoricalExchangeRateQuery) {
            $dateStringified = $responsePayload['date'];
            $date = new DateTime($dateStringified);
            $rate = $responsePayload['results'][$keyAsCurrencyPair]['val'][$dateStringified];
        } else {
            $date = new DateTime('now');
            $rate = $responsePayload['results'][$keyAsCurrencyPair]['val'];
        }

        return new ExchangeRate((string) $rate, $date);
    }

    private function isEnterprise()
    {
        return (bool) $this->options['enterprise'];
    }

    private function getEarliestAvailableDateForHistoricalQuery()
    {
        if ($this->isEnterprise()) {
            return (new DateTime())->setTimestamp(0);
        }

        // Historical rates for free plan is available only for the past year.
        return new DateTime('-1 year 00:00');
    }

    private function stringifyCurrencyPair(CurrencyPair $currencyPair)
    {
        return "{$currencyPair->getBaseCurrency()}_{$currencyPair->getQuoteCurrency()}";
    }

    /**
     * CurrencyConverter API service uses 'Asia/Manila' timezone (UTC+8).
     * For this reason we should do some date-time corrections.
     *
     * @param DateTimeInterface $dateTime
     *
     * @return DateTimeInterface
     */
    private function getAdoptedDateTime(DateTimeInterface $dateTime)
    {
        return (new DateTime())->setTimestamp($dateTime->getTimestamp())->setTimezone(new DateTimeZone('Asia/Manila'));
    }
}
