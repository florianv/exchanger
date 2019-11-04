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

use DateTimeImmutable;
use DateTimeInterface;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Bulgarian National Bank (BNB) Service.
 *
 * @author Marin Bezhanov
 */
final class BulgarianNationalBank extends HttpService
{
    use SupportsHistoricalQueries;

    const URL = 'http://bnb.bg/Statistics/StExternalSector/StExchangeRates/StERForeignCurrencies/index.htm?downloadOper=true&group1=first&firstDays=%s&firstMonths=%s&firstYear=%s&search=true&type=XML';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        return $this->doCreateRate($exchangeQuery, new DateTimeImmutable());
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        return $this->doCreateRate($exchangeQuery, $exchangeQuery->getDate());
    }

    /**
     * Creates the rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     * @param DateTimeInterface $requestedDate
     *
     * @return ExchangeRate
     *
     * @throws UnsupportedCurrencyPairException
     * @throws UnsupportedDateException
     */
    private function doCreateRate(ExchangeRateQuery $exchangeQuery, DateTimeInterface $requestedDate): ExchangeRate
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $baseCurrency = $currencyPair->getBaseCurrency();
        $content = $this->request($this->buildUrl($requestedDate));

        try {
            $element = StringUtil::xmlToElement($content);
        } catch (\RuntimeException $e) {
            // BNB returns an HTTP document when there is no currency information for the specified date.
            throw new UnsupportedDateException($requestedDate, $this);
        }
        $elements = $element->xpath('./ROW[CODE="'.$baseCurrency.'"]');

        if (!isset($elements['0'])) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }
        $date = \DateTime::createFromFormat('!d.m.Y', (string) $elements['0']->CURR_DATE);
        $rate = str_replace(',', '.', (string) $elements['0']->RATE);
        $ratio = str_replace(',', '.', (string) $elements['0']->RATIO);

        if (!$date) {
            throw new UnsupportedDateException($requestedDate, $this);
        }

        if (!$rate || !$ratio) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        return $this->createRate($currencyPair, (float) $rate / $ratio, $date);
    }

    /**
     * @param DateTimeInterface $requestedDate
     *
     * @return string
     */
    private function buildUrl(DateTimeInterface $requestedDate): string
    {
        return sprintf(self::URL, $requestedDate->format('d'), $requestedDate->format('m'), $requestedDate->format('Y'));
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return 'BGN' === $exchangeQuery->getCurrencyPair()->getQuoteCurrency();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'bulgarian_national_bank';
    }
}
