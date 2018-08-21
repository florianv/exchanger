<?php

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

/**
 * Central Bank of the Republic of Turkey (CBRT) Service.
 *
 * @author Uğur Erkan <mail@ugurerkan.com>
 * @author Florian Voutzinos <florian@voutzinos.com>
 * @author Uğur Özkan
 */
class CentralBankOfRepublicTurkey extends HistoricalService
{
    const BASE_URL = 'http://www.tcmb.gov.tr/kurlar/';

    const FILE_EXTENSION = '.xml';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        return $this->createRate($exchangeQuery);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery)
    {
        return $this->createRate($exchangeQuery, $exchangeQuery->getDate());
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeRateQuery)
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
    private function createRate(ExchangeRateQuery $exchangeQuery, DateTimeInterface $requestedDate = null)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $element = $this->getLatestRate($requestedDate);

        $date = new \DateTime((string) $element->xpath('//Tarih_Date/@Date')[0]);
        $elements = $element->xpath('//Currency[@CurrencyCode="'.$currencyPair->getBaseCurrency().'"]/ForexSelling');

        if (!empty($elements) || !$date) {
            return new ExchangeRate((string) $elements[0], $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * Get the latest valid rate. CBRT does not have rates for holidays and weekends.
     *
     * @param DateTimeInterface $requestedDate
     *
     * @return \SimpleXMLElement
     */
    private function getLatestRate(DateTimeInterface $requestedDate = null)
    {
        $rate = $this->fetchRate($requestedDate);

        if (null === $rate) {
            if (null === $requestedDate || $this->isFuture($requestedDate)) {
                $requestedDate = new \DateTime();
            }

            for ($i = 0; $i <= 12; ++$i) {
                $requestedDate->sub(new \DateInterval('P1D'));

                if ($this->isWeekend($requestedDate)) {
                    continue;
                }

                $rate = $this->fetchRate($requestedDate);
                if (!is_null($rate)) {
                    return $rate;
                }
            }
        }

        return $rate;
    }

    /**
     * Checks whether the given date is in the future.
     *
     * @param $requestedDate
     *
     * @return bool
     */
    private function isFuture($requestedDate)
    {
        return $requestedDate > (new \DateTime());
    }

    /**
     * Checks whether the given date is weekend.
     *
     * @param DateTimeInterface $date
     *
     * @return bool
     */
    private function isWeekend(DateTimeInterface $date)
    {
        return 0 === $date->format('w') || 6 === $date->format('w');
    }

    /**
     * Fetch the rate from CBRT.
     *
     * @param DateTimeInterface $requestedDate
     *
     * @return \SimpleXMLElement
     */
    private function fetchRate(DateTimeInterface $requestedDate)
    {
        try {
            $content = $this->request($this->buildUrl($requestedDate));
            return StringUtil::xmlToElement($content);
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * Builds the url.
     *
     * @param DateTimeInterface|null $requestedDate
     *
     * @return string
     */
    private function buildUrl(DateTimeInterface $requestedDate = null)
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
}
