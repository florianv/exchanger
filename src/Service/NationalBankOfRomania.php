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
 * National Bank of Romania Service.
 *
 * @author Mihai Zaharie <mihai@zaharie.ro>
 * @author Florian Voutzinos <florian@voutzinos.com>
 * @author Balazs Csaba <balazscsaba2006@gmail.com>
 */
class NationalBankOfRomania extends HistoricalService
{
    const URL = 'http://www.bnr.ro/nbrfxrates.xml';

    const HISTORICAL_URL_TEMPLATE = 'http://www.bnr.ro/files/xml/years/nbrfxrates{year}.xml';

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedCurrencyPairException
     */
    public function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $content = $this->request(self::URL);

        $element = StringUtil::xmlToElement($content);
        $element->registerXPathNamespace('xmlns', 'http://www.bnr.ro/xsd');

        $currencyPair = $exchangeQuery->getCurrencyPair();
        $date = new \DateTime((string) $element->xpath('//xmlns:PublishingDate')[0]);
        $xmlCurrency = 'RON' === $currencyPair->getBaseCurrency() ? $currencyPair->getQuoteCurrency() : $currencyPair->getBaseCurrency();

        $elements = $element->xpath('//xmlns:Rate[@currency="'.$xmlCurrency.'"]');

        if (empty($elements) || !$date) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $element = $elements[0];
        $rate = (string) $element;
        $rateValue = !empty($element['multiplier']) ? $rate / (int) $element['multiplier'] : $rate;

        if ('RON' === $currencyPair->getBaseCurrency()) {
            $rateValue = number_format(1 / $rateValue, 4, '.', '');
        }

        return new ExchangeRate((string) $rateValue, $date);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedDateException
     * @throws UnsupportedCurrencyPairException
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery)
    {
        $year = $exchangeQuery->getDate()->format('Y');
        if ($year < 2005) {
            throw new UnsupportedDateException($exchangeQuery->getDate(), $this);
        }

        $url = str_replace('{year}', $year, self::HISTORICAL_URL_TEMPLATE);
        $content = $this->request($url);

        // remove BOM from beginning of content
        $content = substr($content, strpos($content, '<'));

        $element = StringUtil::xmlToElement($content);
        $element->registerXPathNamespace('xmlns', 'http://www.bnr.ro/xsd');

        $formattedDate = $exchangeQuery->getDate()->format('Y-m-d');
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $xmlCurrency = 'RON' === $currencyPair->getBaseCurrency() ? $currencyPair->getQuoteCurrency() : $currencyPair->getBaseCurrency();

        $elements = $element->xpath('//xmlns:Cube[@date="'.$formattedDate.'"]/xmlns:Rate[@currency="'.$xmlCurrency.'"]');

        if (empty($elements)) {
            if (empty($element->xpath('//xmlns:Cube[@date="'.$formattedDate.'"]'))) {
                throw new UnsupportedDateException($exchangeQuery->getDate(), $this);
            }

            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $element = $elements[0];
        $rate = (string) $element;
        $rateValue = !empty($element['multiplier']) ? $rate / (int) $element['multiplier'] : $rate;

        if ('RON' === $currencyPair->getBaseCurrency()) {
            $rateValue = number_format(1 / $rateValue, 4, '.', '');
        }

        return new ExchangeRate((string) $rateValue, $exchangeQuery->getDate());
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        return 'RON' === $exchangeQuery->getCurrencyPair()->getBaseCurrency()
            || 'RON' === $exchangeQuery->getCurrencyPair()->getQuoteCurrency();
    }
}
