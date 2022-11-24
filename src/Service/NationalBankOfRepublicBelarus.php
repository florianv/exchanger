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

use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\Exception\UnsupportedExchangeQueryException;
use Exchanger\StringUtil;

/**
 * National Bank of the Republic of Belarus (NBRB) Service.
 *
 * @author Sergey Danilchenko <s.danilchenko@ttbooking.ru>
 */
class NationalBankOfRepublicBelarus extends HttpService
{
    use SupportsHistoricalQueries;

    protected const URL = 'https://www.nbrb.by/api/exrates/rates';

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedCurrencyPairException
     * @throws UnsupportedDateException
     * @throws UnsupportedExchangeQueryException
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
     * @throws UnsupportedExchangeQueryException
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        return $this->doCreateRate($exchangeQuery, $exchangeQuery->getDate());
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $ignoreSupportPeriod
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery, bool $ignoreSupportPeriod = false): bool
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $baseCurrency = $currencyPair->getBaseCurrency();
        $quoteCurrency = $currencyPair->getQuoteCurrency();
        $date = $exchangeQuery instanceof HistoricalExchangeRateQuery && !$ignoreSupportPeriod
            ? $exchangeQuery->getDate() : null;

        return is_int(self::detectPeriodicity($baseCurrency, $date))
            && self::supportQuoteCurrency($quoteCurrency, $date);
    }

    /**
     * Tells if the service supports base currency for the given date and detect its periodicity if it does.
     *
     * @param string $baseCurrency
     * @param \DateTimeInterface|null $date
     *
     * @return int|false
     */
    private static function detectPeriodicity(string $baseCurrency, \DateTimeInterface $date = null)
    {
        return array_reduce(

            array_reverse(array_intersect_key(
                $codes = self::getSupportedCodes(),
                array_flip(array_keys(array_column($codes, 'Cur_Abbreviation'), $baseCurrency))
            )),

            static function ($periodicity, $entry) use ($date) {
                if ($date) {
                    $dateStart = new \DateTimeImmutable($entry['Cur_DateStart']);
                    $dateEnd = new \DateTimeImmutable($entry['Cur_DateEnd']);
                    if ($date < $dateStart || $date > $dateEnd) {
                        return $periodicity;
                    }
                }

                return in_array($periodicity, [false, 1], true) ? $entry['Cur_Periodicity'] : $periodicity;
            },

            false

        );
    }

    /**
     * Tells if the service supports quote currency for the given date.
     *
     * @param string $quoteCurrency
     * @param \DateTimeInterface|null $date
     *
     * @return bool
     */
    private static function supportQuoteCurrency(string $quoteCurrency, \DateTimeInterface $date = null): bool
    {
        if ($date) {
            $date = $date->format('Y-m-d');
        }

        return $date
            ? $quoteCurrency === 'BYN' && $date >= '2016-07-01'
                || $quoteCurrency === 'BYR' && $date >= '2000-01-01' && $date < '2016-07-01'
                || $quoteCurrency === 'BYB' && $date >= '1992-05-25' && $date < '2000-01-01'
            : in_array($quoteCurrency, ['BYN', 'BYR', 'BYB']);
    }

    /**
     * Array of base currency codes supported by the service.
     *
     * @url https://www.nbrb.by/api/exrates/currencies
     *
     * @return list<array{
     *     Cur_Abbreviation: string,
     *     Cur_Periodicity: int,
     *     Cur_DateStart: string,
     *     Cur_DateEnd: string
     * }>
     */
    private static function getSupportedCodes(): array
    {
        static $codes;

        return $codes = $codes ?? StringUtil::jsonToArray(file_get_contents(__DIR__.'/resources/nbrb-codes.json'));
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'national_bank_of_republic_belarus';
    }

    /**
     * Creates the rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     * @param \DateTimeInterface|null $requestedDate
     *
     * @return ExchangeRate
     *
     * @throws UnsupportedCurrencyPairException
     * @throws UnsupportedDateException
     * @throws UnsupportedExchangeQueryException
     */
    private function doCreateRate(ExchangeRateQuery $exchangeQuery, \DateTimeInterface $requestedDate = null): ExchangeRate
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $baseCurrency = $currencyPair->getBaseCurrency();

        if (!$this->supportQuery($exchangeQuery, true)) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        if ($requestedDate && $requestedDate->format('Y-m-d') < '1995-03-29') {
            throw new UnsupportedDateException($requestedDate, $this);
        }

        $content = $this->request($this->buildUrl($baseCurrency, $requestedDate));
        $result = StringUtil::jsonToArray($content);
        $entryId = array_search($baseCurrency, array_column($result, 'Cur_Abbreviation'));

        if ($entryId === false) {
            throw new UnsupportedExchangeQueryException($exchangeQuery, $this);
        }

        /**
         * @var array{
         *     Cur_ID: int,
         *     Date: string,
         *     Cur_Abbreviation: string,
         *     Cur_Scale: int,
         *     Cur_Name: string,
         *     Cur_OfficialRate: float
         * } $entry
         */
        $entry = $result[$entryId];

        if (!isset($entry['Cur_OfficialRate'])) {
            throw new \RuntimeException('Service has returned malformed response');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $entry['Date'] ?? null);
        $requestedDate = $requestedDate ?? new \DateTimeImmutable;
        if (!$date || $date->format('Y-m-d') !== $requestedDate->format('Y-m-d')) {
            throw new UnsupportedDateException($requestedDate, $this);
        }

        $rate = $entry['Cur_OfficialRate'];
        $scale = $entry['Cur_Scale'] ?? 1;

        return $this->createRate($currencyPair, $rate / $scale, $date);
    }

    /**
     * Builds the url.
     *
     * @param string $baseCurrency
     * @param \DateTimeInterface|null $requestedDate
     *
     * @return string
     */
    private function buildUrl(string $baseCurrency, \DateTimeInterface $requestedDate = null): string
    {
        $data = isset($requestedDate) ? ['ondate' => $requestedDate->format('Y-m-d')] : [];
        $data += ['periodicity' => (int) self::detectPeriodicity($baseCurrency, $requestedDate)];

        return self::URL.'?'.http_build_query($data);
    }
}
