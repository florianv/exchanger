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

use Exchanger\Contract\CurrencyPair;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\Exception\Exception;
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

    protected const URL = 'https://curs.bnr.ro/nbrfxrates.xml';

    protected const HISTORICAL_URL_TEMPLATE = 'https://curs.bnr.ro/files/xml/years/nbrfxrates{year}.xml';

    private const SUPPORTED_CURRENCIES = [
        'AED',
        'AUD',
        'BGN',
        'BRL',
        'CAD',
        'CHF',
        'CNY',
        'CZK',
        'DKK',
        'EGP',
        'EUR',
        'GBP',
        'HRK',
        'HUF',
        'INR',
        'JPY',
        'KRW',
        'MDL',
        'MXN',
        'NOK',
        'NZD',
        'PLN',
        'RSD',
        'RUB',
        'SEK',
        'TRY',
        'UAH',
        'USD',
        'XAU',
        'XDR',
        'ZAR',
    ];

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedCurrencyPairException
     */
    #[\Override]
    public function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $element = $this->parseXml($this->request(self::URL));

        $currencyPair = $exchangeQuery->getCurrencyPair();
        $publishingDates = StringUtil::xpath($element, '//xmlns:PublishingDate');
        $xmlCurrency = $this->getXmlCurrency($currencyPair);

        $elements = StringUtil::xpath($element, '//xmlns:Rate[@currency="' . $xmlCurrency . '"]');

        if ([] === $publishingDates) {
            throw new Exception('The response of the National Bank of Romania has no rate date.');
        }

        if ([] === $elements) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $date = new \DateTime((string) $publishingDates[0]);
        $rateValue = $this->getRateValue($elements[0], $currencyPair);

        return $this->createRate($currencyPair, $rateValue, $date);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedDateException
     * @throws UnsupportedCurrencyPairException
     */
    #[\Override]
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $date = $exchangeQuery->getDate();
        $year = $date->format('Y');

        if ($year < 2005) {
            throw new UnsupportedDateException($date, $this);
        }

        $url = str_replace('{year}', $year, self::HISTORICAL_URL_TEMPLATE);
        $element = $this->parseXml($this->request($url));

        $formattedDate = $date->format('Y-m-d');
        $xmlCurrency = $this->getXmlCurrency($currencyPair);

        $elements = StringUtil::xpath($element, '//xmlns:Cube[@date="' . $formattedDate . '"]/xmlns:Rate[@currency="' . $xmlCurrency . '"]');

        if ([] === $elements) {
            if ([] === StringUtil::xpath($element, '//xmlns:Cube[@date="' . $formattedDate . '"]')) {
                throw new UnsupportedDateException($date, $this);
            }

            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rateValue = $this->getRateValue($elements[0], $currencyPair);

        return $this->createRate($currencyPair, $rateValue, $date);
    }

    /** {@inheritdoc} */
    #[\Override]
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        $base = $exchangeQuery->getCurrencyPair()->getBaseCurrency();
        $quote = $exchangeQuery->getCurrencyPair()->getQuoteCurrency();

        return ('RON' === $base && \in_array($quote, self::SUPPORTED_CURRENCIES))
            || ('RON' === $quote && \in_array($base, self::SUPPORTED_CURRENCIES));
    }

    /** {@inheritdoc} */
    #[\Override]
    public function getName(): string
    {
        return 'national_bank_of_romania';
    }

    /**
     * Parses the XML content, registering the "xmlns" XPath prefix with the namespace
     * declared by the document: BNR changed it from "http://www.bnr.ro/xsd" to
     * "https://www.bnr.ro/xsd", and hardcoding either would break the other.
     *
     * @param string $content
     *
     * @return \SimpleXMLElement
     */
    private function parseXml(string $content): \SimpleXMLElement
    {
        // skip any BOM or other leading noise before the XML declaration
        $content = substr($content, (int) strpos($content, '<'));

        $element = StringUtil::xmlToElement($content);

        $namespaces = $element->getDocNamespaces();
        $element->registerXPathNamespace('xmlns', \is_array($namespaces) && isset($namespaces['']) ? $namespaces[''] : 'https://www.bnr.ro/xsd');

        return $element;
    }

    /**
     * @param CurrencyPair $currencyPair
     *
     * @return string
     */
    private function getXmlCurrency(CurrencyPair $currencyPair): string
    {
        return 'RON' === $currencyPair->getBaseCurrency()
            ? $currencyPair->getQuoteCurrency()
            : $currencyPair->getBaseCurrency();
    }

    /**
     * @param \SimpleXMLElement $element
     * @param CurrencyPair      $currencyPair
     *
     * @return float
     */
    private function getRateValue(\SimpleXMLElement $element, CurrencyPair $currencyPair): float
    {
        $rate = (float) (string) $element;
        $multiplier = (float) (string) $element['multiplier'];
        $rateValue = $multiplier > 0 ? $rate / $multiplier : $rate;

        if ('RON' === $currencyPair->getBaseCurrency()) {
            $rateValue = round(1.0 / $rateValue, 4);
        }

        return $rateValue;
    }
}
