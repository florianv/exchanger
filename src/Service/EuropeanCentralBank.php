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

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;

/**
 * European Central Bank Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class EuropeanCentralBank extends HistoricalService
{
    const DAILY_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    const HISTORICAL_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist-90d.xml';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $content = $this->request(self::DAILY_URL);

        $element = StringUtil::xmlToElement($content);
        $element->registerXPathNamespace('xmlns', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');

        $quoteCurrency = $exchangeQuery->getCurrencyPair()->getQuoteCurrency();
        $elements = $element->xpath('//xmlns:Cube[@currency="'.$quoteCurrency.'"]/@rate');
        $date = new \DateTime((string) $element->xpath('//xmlns:Cube[@time]/@time')[0]);

        if (empty($elements) || !$date) {
            throw new UnsupportedCurrencyPairException($exchangeQuery->getCurrencyPair(), $this);
        }

        return new ExchangeRate((string) $elements[0]['rate'], $date);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery)
    {
        $content = $this->request(self::HISTORICAL_URL);

        $element = StringUtil::xmlToElement($content);
        $element->registerXPathNamespace('xmlns', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');

        $formattedDate = $exchangeQuery->getDate()->format('Y-m-d');
        $quoteCurrency = $exchangeQuery->getCurrencyPair()->getQuoteCurrency();

        $elements = $element->xpath('//xmlns:Cube[@time="'.$formattedDate.'"]/xmlns:Cube[@currency="'.$quoteCurrency.'"]/@rate');

        if (empty($elements)) {
            if (empty($element->xpath('//xmlns:Cube[@time="'.$formattedDate.'"]'))) {
                throw new UnsupportedDateException($exchangeQuery->getDate(), $this);
            }

            throw new UnsupportedCurrencyPairException($exchangeQuery->getCurrencyPair(), $this);
        }

        return new ExchangeRate((string) $elements[0]['rate'], $exchangeQuery->getDate());
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        return 'EUR' === $exchangeQuery->getCurrencyPair()->getBaseCurrency();
    }
}
