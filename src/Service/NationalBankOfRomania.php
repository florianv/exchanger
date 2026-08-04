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
        $publishingDates = $element->xpath('//xmlns:PublishingDate');
        $xmlCurrency = $this->getXmlCurrency($currencyPair);

        $elements = $element->xpath('//xmlns:Rate[@currency="' . $xmlCurrency . '"]');

        if (empty($elements) || empty($publishingDates)) {
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

        $elements = $element->xpath('//xmlns:Cube[@date="' . $formattedDate . '"]/xmlns:Rate[@currency="' . $xmlCurrency . '"]');

        if (empty($elements)) {
            if (empty($element->xpath('//xmlns:Cube[@date="' . $formattedDate . '"]'))) {
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
        // remove BOM from beginning of content
        $content = substr($content, (int) strpos($content, '<'));

        $element = StringUtil::xmlToElement($content);

        $namespaces = $element->getDocNamespaces();
        $namespace = \is_array($namespaces) ? ($namespaces[''] ?? null) : null;
        $element->registerXPathNamespace('xmlns', $namespace ?? 'https://www.bnr.ro/xsd');

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
        $rate = (string) $element;
        $rateValue = (!empty($element['multiplier'])) ? $rate / (int) $element['multiplier'] : $rate;

        if ('RON' === $currencyPair->getBaseCurrency()) {
            $rateValue = number_format(1 / $rateValue, 4, '.', '');
        }

        return (float) $rateValue;
    }
}
