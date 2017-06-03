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
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;

/**
 * Central Bank of Republic of Turkey (CBRT) Service.
 *
 * @author Uğur Erkan <mail@ugurerkan.com>
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class CentralBankOfRepublicTurkey extends Service
{
    const URL = 'http://www.tcmb.gov.tr/kurlar/today.xml';

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeRateQuery)
    {
        $currencyPair = $exchangeRateQuery->getCurrencyPair();
        $content = $this->request(self::URL);

        $element = StringUtil::xmlToElement($content);

        $date = new \DateTime((string) $element->xpath('//Tarih_Date/@Date')[0]);
        $elements = $element->xpath('//Currency[@CurrencyCode="'.$currencyPair->getBaseCurrency().'"]/ForexSelling');

        if (!empty($elements) || !$date) {
            return new ExchangeRate((string) $elements[0], $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeRateQuery)
    {
        return !$exchangeRateQuery instanceof HistoricalExchangeRateQuery
        && 'TRY' === $exchangeRateQuery->getCurrencyPair()->getQuoteCurrency();
    }
}
