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
use Exception;
use Exchanger\Contract\CurrencyPair;
use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\StringUtil;
use InvalidArgumentException;

/**
 * National Bank of Poland (NBP) Service.
 *
 * Uses the NBP Web API (https://api.nbp.pl) which publishes the average (mid) rates
 * of table "A" (convertible currencies, the default) and table "B" (inconvertible
 * currencies). Rates are quoted in PLN per one unit of the foreign currency, so no
 * normalization is needed. The table can be selected with the "table" option:
 *
 *     new NationalBankOfPoland($client, null, ['table' => 'b']);
 */
final class NationalBankOfPoland extends HttpService
{
    use SupportsHistoricalQueries;

    private const LATEST_URL = 'https://api.nbp.pl/api/exchangerates/rates/%s/%s/?format=json';

    private const HISTORICAL_URL = 'https://api.nbp.pl/api/exchangerates/rates/%s/%s/%s/?format=json';

    /**
     * The tables holding average (mid) rates.
     */
    private const SUPPORTED_TABLES = ['a', 'b'];

    /**
     * The first day for which the service publishes rates.
     */
    private const EARLIEST_DATE = '2002-01-02';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options['table'])) {
            $options['table'] = self::SUPPORTED_TABLES[0];
        }

        $options['table'] = strtolower((string)$options['table']);

        if (!in_array($options['table'], self::SUPPORTED_TABLES, true)) {
            throw new InvalidArgumentException(
                sprintf('The "table" option must be one of "%s".', implode('", "', self::SUPPORTED_TABLES))
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if (('PLN' === $currencyPair->getBaseCurrency()) === ('PLN' === $currencyPair->getQuoteCurrency())) {
            return false;
        }

        if ($exchangeQuery instanceof HistoricalExchangeRateQuery) {
            $date = $exchangeQuery->getDate();

            if ($date > new DateTime('now') || $date < new DateTime(self::EARLIEST_DATE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedCurrencyPairException|UnsupportedDateException
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        return $this->doCreateRate($exchangeQuery);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedCurrencyPairException
     * @throws UnsupportedDateException
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        return $this->doCreateRate($exchangeQuery, $exchangeQuery->getDate());
    }

    /**
     * Creates the rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     * @param DateTimeInterface|null $requestedDate
     *
     * @return ExchangeRate
     *
     * @throws UnsupportedCurrencyPairException
     * @throws UnsupportedDateException
     * @throws Exception
     */
    private function doCreateRate(
        ExchangeRateQuery $exchangeQuery,
        ?DateTimeInterface $requestedDate = null
    ): ExchangeRate {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $response = $this->getResponse($this->buildUrl($currencyPair, $requestedDate));

        if (200 !== $response->getStatusCode()) {
            // The service answers with a 404 both for a currency missing from the
            // requested table and for a day without a published table (weekends,
            // bank holidays), without telling the two cases apart.
            if (null !== $requestedDate) {
                throw new UnsupportedDateException($requestedDate, $this);
            }

            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $data = StringUtil::jsonToArray($response->getBody()->__toString());

        if (!isset($data['rates'][0]['mid'], $data['rates'][0]['effectiveDate'])) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rate = (float)$data['rates'][0]['mid'];
        $date = new DateTime((string)$data['rates'][0]['effectiveDate']);

        if (0.0 === $rate) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        // The service only quotes foreign currencies against PLN.
        if ('PLN' === $currencyPair->getBaseCurrency()) {
            $rate = 1 / $rate;
        }

        return $this->createRate($currencyPair, $rate, $date);
    }

    /**
     * Builds the url.
     *
     * @param CurrencyPair $currencyPair
     * @param DateTimeInterface|null $requestedDate
     *
     * @return string
     */
    private function buildUrl(CurrencyPair $currencyPair, ?DateTimeInterface $requestedDate = null): string
    {
        $currencyCode = strtolower($this->getForeignCurrency($currencyPair));

        if (null === $requestedDate) {
            return sprintf(self::LATEST_URL, $this->options['table'], $currencyCode);
        }

        return sprintf(
            self::HISTORICAL_URL,
            $this->options['table'],
            $currencyCode,
            $requestedDate->format('Y-m-d')
        );
    }

    /**
     * Gets the currency of the pair which is quoted against PLN.
     *
     * @param CurrencyPair $currencyPair
     *
     * @return string
     */
    private function getForeignCurrency(CurrencyPair $currencyPair): string
    {
        return 'PLN' === $currencyPair->getBaseCurrency()
            ? $currencyPair->getQuoteCurrency()
            : $currencyPair->getBaseCurrency();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'narodowy_bank_polski';
    }
}
