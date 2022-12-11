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

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exchanger\Contract\CurrencyPair;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Currency Converter API service.
 *
 * @author Aliaksandr lptn
 */
final class CurrencyConverter extends HttpService
{
    use SupportsHistoricalQueries;

    const FREE_LATEST_URL = 'https://free.currconv.com/api/v7/convert?q=%s&apiKey=%s';

    const ENTERPRISE_LATEST_URL = 'https://api.currconv.com/api/v7/convert?q=%s&apiKey=%s';

    const FREE_HISTORICAL_URL = 'https://free.currconv.com/api/v7/convert?q=%s&date=%s&apiKey=%s';

    const ENTERPRISE_HISTORICAL_URL = 'https://api.currconv.com/api/v7/convert?q=%s&date=%s&apiKey=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options['enterprise'])) {
            $options['enterprise'] = false;
        }

        if (!isset($options['access_key'])) {
            throw new \InvalidArgumentException('The "access_key" option must be provided.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeRateQuery): bool
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
     * @return ExchangeRateContract
     *
     * @throws Exception
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        if ($this->isEnterprise()) {
            $url = sprintf(
                self::ENTERPRISE_LATEST_URL,
                $this->stringifyCurrencyPair($exchangeQuery->getCurrencyPair()),
                $this->options['access_key']
            );
        } else {
            $url = sprintf(
                self::FREE_LATEST_URL,
                $this->stringifyCurrencyPair($exchangeQuery->getCurrencyPair()),
                $this->options['access_key']
            );
        }

        return $this->fetchOnlineRate($url, $exchangeQuery);
    }

    /**
     * Gets an historical rate.
     *
     * @param HistoricalExchangeRateQuery $exchangeQuery
     *
     * @return ExchangeRateContract
     *
     * @throws Exception
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $historicalDateTime = $this->getAdoptedDateTime($exchangeQuery->getDate());

        if ($this->isEnterprise()) {
            $url = sprintf(
                self::ENTERPRISE_HISTORICAL_URL,
                $this->stringifyCurrencyPair($exchangeQuery->getCurrencyPair()),
                $historicalDateTime->format('Y-m-d'),
                $this->options['access_key']
            );
        } else {
            $url = sprintf(
                self::FREE_HISTORICAL_URL,
                $this->stringifyCurrencyPair($exchangeQuery->getCurrencyPair()),
                $historicalDateTime->format('Y-m-d'),
                $this->options['access_key']
            );
        }

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
    private function fetchOnlineRate($url, ExchangeRateQuery $exchangeRateQuery): ExchangeRate
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
            $date = new \DateTime($dateStringified);
            $rate = $responsePayload['results'][$keyAsCurrencyPair]['val'][$dateStringified];
        } else {
            $date = new \DateTime('now');
            $rate = $responsePayload['results'][$keyAsCurrencyPair]['val'];
        }

        return $this->createRate($currencyPair, (float) $rate, $date);
    }

    /**
     * Tells if the entreprise mode is used.
     *
     * @return bool
     */
    private function isEnterprise(): bool
    {
        return (bool) $this->options['enterprise'];
    }

    /**
     * Gets the earliest available date for the historical query.
     *
     * @return \DateTime
     */
    private function getEarliestAvailableDateForHistoricalQuery(): \DateTime
    {
        if ($this->isEnterprise()) {
            return (new \DateTime())->setTimestamp(0);
        }

        // Historical rates for free plan is available only for the past year.
        return new \DateTime('-1 year 00:00');
    }

    /**
     * Helper function to stringify a currency pair.
     *
     * @param CurrencyPair $currencyPair
     *
     * @return string
     */
    private function stringifyCurrencyPair(CurrencyPair $currencyPair): string
    {
        return "{$currencyPair->getBaseCurrency()}_{$currencyPair->getQuoteCurrency()}";
    }

    /**
     * CurrencyConverter API service uses 'Asia/Manila' timezone (UTC+8).
     * For this reason we should do some date-time corrections.
     *
     * @param DateTimeInterface $dateTime
     *
     * @return \DateTime
     */
    private function getAdoptedDateTime(DateTimeInterface $dateTime): \DateTime
    {
        return (new \DateTime())
            ->setTimestamp($dateTime->getTimestamp())
            ->setTimezone(new DateTimeZone('Asia/Manila'));
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'currency_converter';
    }
}
