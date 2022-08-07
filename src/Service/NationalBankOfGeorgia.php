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
use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\StringUtil;

/**
 * National Bank of Georgia Service.
 *
 * @author Uğur Özkan
 */
final class NationalBankOfGeorgia extends HttpService
{
    use SupportsHistoricalQueries;

    private const BASE_URL = 'https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/en/json';

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'national_bank_of_georgia';
    }

    /**
     * @inheritDoc
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return 'GEL' === $exchangeQuery->getCurrencyPair()->getQuoteCurrency();
    }

    /**
     * @inheritDoc
     * @throws UnsupportedCurrencyPairException
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        return $this->doCreateRate($exchangeQuery);
    }

    /**
     * @inheritDoc
     * @throws UnsupportedCurrencyPairException
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
     * @throws UnsupportedCurrencyPairException
     * @throws Exception
     */
    private function doCreateRate(ExchangeRateQuery $exchangeQuery, DateTimeInterface $requestedDate = null): ExchangeRate
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $content = $this->request($this->buildUrl($requestedDate));
        $element = StringUtil::jsonToArray($content)[0];

        $date = new DateTime((string) $element['date']);

        $currencyInfo = array_values(array_filter($element['currencies'], function ($currency) use ($currencyPair) {
            return $currency['code'] === $currencyPair->getBaseCurrency();
        }));
        if (!empty($currencyInfo)) {
            $rate = (float) $currencyInfo[0]['rate'];
            $unit = (int) $currencyInfo[0]['quantity'];

            return $this->createRate($currencyPair, ($rate / $unit), $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * Builds the url.
     *
     * @param DateTimeInterface|null $requestedDate
     *
     * @return string
     */
    private function buildUrl(DateTimeInterface $requestedDate = null): string
    {
        $date = '';
        if (!is_null($requestedDate)) {
            $date = '?date=' . $requestedDate->format('Y-m-d');
        }

        return self::BASE_URL . $date;
    }
}
