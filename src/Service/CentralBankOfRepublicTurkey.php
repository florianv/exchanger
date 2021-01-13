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

use DateTimeInterface;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Central Bank of the Republic of Turkey (CBRT) Service.
 *
 * @author Uğur Erkan <mail@ugurerkan.com>
 * @author Florian Voutzinos <florian@voutzinos.com>
 * @author Uğur Özkan
 */
final class CentralBankOfRepublicTurkey extends HttpService
{
    use SupportsHistoricalQueries;

    const BASE_URL = 'https://www.tcmb.gov.tr/kurlar/';

    const FILE_EXTENSION = '.xml';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        return $this->doCreateRate($exchangeQuery);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        return $this->doCreateRate($exchangeQuery, $exchangeQuery->getDate());
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeRateQuery): bool
    {
        return 'TRY' === $exchangeRateQuery->getCurrencyPair()->getQuoteCurrency();
    }

    /**
     * Creates the rate.
     *
     * @param ExchangeRateQuery      $exchangeQuery
     * @param DateTimeInterface|null $requestedDate
     *
     * @return ExchangeRate
     *
     * @throws UnsupportedCurrencyPairException
     */
    private function doCreateRate(ExchangeRateQuery $exchangeQuery, DateTimeInterface $requestedDate = null): ExchangeRate
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $content = $this->request($this->buildUrl($requestedDate));

        $element = StringUtil::xmlToElement($content);

        $date = new \DateTime((string) $element->xpath('//Tarih_Date/@Date')[0]);
        $elements = $element->xpath('//Currency[@CurrencyCode="'.$currencyPair->getBaseCurrency().'"]');

        if (!empty($elements) || !$date) {
            $rate = (float) $elements[0]->ForexSelling;
            $unit = (int) $elements[0]->Unit ?? 1;

            return $this->createRate($currencyPair, (float) ($rate / $unit), $date);
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
        if (null === $requestedDate) {
            $fileName = 'today';
        } else {
            $yearMonth = $requestedDate->format('Ym');
            $dayMonthYear = $requestedDate->format('dmY');
            $fileName = "$yearMonth/$dayMonthYear";
        }

        return self::BASE_URL.$fileName.self::FILE_EXTENSION;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'central_bank_of_republic_turkey';
    }
}
