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

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * European Central Bank Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class EuropeanCentralBank extends HttpService
{
    use SupportsHistoricalQueries;

    const DAILY_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    const HISTORICAL_URL_LIMITED_TO_90_DAYS_BACK = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist-90d.xml';

    const HISTORICAL_URL_OLDER_THAN_90_DAYS = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist.xml';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $content = $this->request(self::DAILY_URL);

        $element = StringUtil::xmlToElement($content);
        $element->registerXPathNamespace('xmlns', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');

        $quoteCurrency = $currencyPair->getQuoteCurrency();
        $elements = $element->xpath('//xmlns:Cube[@currency="'.$quoteCurrency.'"]/@rate');
        $date = new \DateTime((string) $element->xpath('//xmlns:Cube[@time]/@time')[0]);

        if (empty($elements) || !$date) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        return $this->createRate($currencyPair, (float) ($elements[0]['rate']), $date);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $historicalUrl = $this->getHistoricalUrl($exchangeQuery->getDate());
        $content = $this->request($historicalUrl);

        $element = StringUtil::xmlToElement($content);
        $element->registerXPathNamespace('xmlns', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');

        $formattedDate = $exchangeQuery->getDate()->format('Y-m-d');
        $quoteCurrency = $currencyPair->getQuoteCurrency();

        $elements = $element->xpath('//xmlns:Cube[@time="'.$formattedDate.'"]/xmlns:Cube[@currency="'.$quoteCurrency.'"]/@rate');

        if (empty($elements)) {
            if (empty($element->xpath('//xmlns:Cube[@time="'.$formattedDate.'"]'))) {
                throw new UnsupportedDateException($exchangeQuery->getDate(), $this);
            }

            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        return $this->createRate($currencyPair, (float) ($elements[0]['rate']), $exchangeQuery->getDate());
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return 'EUR' === $exchangeQuery->getCurrencyPair()->getBaseCurrency();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'european_central_bank';
    }

    /**
     * @param \DateTimeInterface $date
     *
     * @return string
     *
     * @throws \Exception
     */
    private function getHistoricalUrl(\DateTimeInterface $date): string
    {
        $dateDiffInDays = $date->diff(new \DateTime('now'))->days;
        if ($dateDiffInDays > 90) {
            return self::HISTORICAL_URL_OLDER_THAN_90_DAYS;
        }

        return self::HISTORICAL_URL_LIMITED_TO_90_DAYS_BACK;
    }
}
