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
 * National Bank of Romania Service.
 *
 * @author Mihai Zaharie <mihai@zaharie.ro>
 * @author Florian Voutzinos <florian@voutzinos.com>
 * @author Balazs Csaba <balazscsaba2006@gmail.com>
 */
final class NationalBankOfRomania extends HttpService
{
    use SupportsHistoricalQueries;

    const URL = 'https://www.bnr.ro/nbrfxrates.xml';

    const HISTORICAL_URL_TEMPLATE = 'https://www.bnr.ro/files/xml/years/nbrfxrates{year}.xml';

    /**
     * {@inheritdoc}
     */
    public function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $content = $this->request(self::URL);

        $element = StringUtil::xmlToElement($content);
        $element->registerXPathNamespace('xmlns', 'http://www.bnr.ro/xsd');

        $currencyPair = $exchangeQuery->getCurrencyPair();
        $date = new \DateTime((string) $element->xpath('//xmlns:PublishingDate')[0]);
        $elements = $element->xpath('//xmlns:Rate[@currency="'.$currencyPair->getBaseCurrency().'"]');

        if (empty($elements) || !$date) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $element = $elements[0];
        $rate = (string) $element;
        $rateValue = (!empty($element['multiplier'])) ? $rate / (int) $element['multiplier'] : $rate;

        return $this->createRate($currencyPair, (float) $rateValue, $date);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $date = $exchangeQuery->getDate();
        $year = $date->format('Y');

        if ($year < 2005) {
            throw new UnsupportedDateException($date, $this);
        }

        $url = str_replace('{year}', $year, self::HISTORICAL_URL_TEMPLATE);
        $content = $this->request($url);

        // remove BOM from beginning of content
        $content = substr($content, strpos($content, '<'));

        $element = StringUtil::xmlToElement($content);
        $element->registerXPathNamespace('xmlns', 'http://www.bnr.ro/xsd');

        $formattedDate = $date->format('Y-m-d');
        $baseCurrency = $currencyPair->getBaseCurrency();

        $elements = $element->xpath('//xmlns:Cube[@date="'.$formattedDate.'"]/xmlns:Rate[@currency="'.$baseCurrency.'"]');

        if (empty($elements)) {
            if (empty($element->xpath('//xmlns:Cube[@date="'.$formattedDate.'"]'))) {
                throw new UnsupportedDateException($date, $this);
            }

            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $element = $elements[0];
        $rate = (string) $element;
        $rateValue = (!empty($element['multiplier'])) ? $rate / (int) $element['multiplier'] : $rate;

        return $this->createRate($currencyPair, (float) $rateValue, $date);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return 'RON' === $exchangeQuery->getCurrencyPair()->getQuoteCurrency();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'national_bank_of_romania';
    }
}
