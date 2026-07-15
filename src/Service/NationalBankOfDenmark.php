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
use Exchanger\Exception\Exception;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * National Bank of Denmark (Danmarks Nationalbank) Service.
 *
 * All rates are published as DKK per 100 units of foreign currency.
 */
final class NationalBankOfDenmark extends HttpService
{
    use SupportsHistoricalQueries;

    public const LATEST_URL = 'https://www.nationalbanken.dk/api/currencyratesxml';

    public const FIVE_DAY_HISTORY_URL = 'https://www.nationalbanken.dk/api/currencyratesxmlhistory';

    public const STATBANK_URL = 'https://api.statbank.dk/v1/data';

    private const SUPPORTED_CURRENCIES = [
        'AUD',
        'BRL',
        'CAD',
        'CHF',
        'CNY',
        'CZK',
        'EUR',
        'GBP',
        'HKD',
        'HUF',
        'IDR',
        'ILS',
        'INR',
        'ISK',
        'JPY',
        'KRW',
        'MXN',
        'MYR',
        'NOK',
        'NZD',
        'PHP',
        'PLN',
        'RON',
        'SEK',
        'SGD',
        'THB',
        'TRY',
        'USD',
        'XDR',
        'ZAR',
    ];

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedCurrencyPairException
     */
    #[\Override]
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $content = $this->request(self::LATEST_URL);

        // remove BOM from beginning of content
        $content = substr($content, (int) strpos($content, '<'));

        $element = StringUtil::xmlToElement($content);

        $foreignCurrency = $this->getForeignCurrency($currencyPair);
        $elements = $element->xpath('//currency[@code="' . $foreignCurrency . '"]/@rate');
        $dates = $element->xpath('//dailyrates/@id');

        if (empty($elements) || empty($dates)) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $date = new \DateTime((string) $dates[0]);
        $rateValue = $this->buildRateValue((float) $elements[0]['rate'], $currencyPair);

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
        $dateDiffInDays = $exchangeQuery->getDate()->diff(new \DateTime('now'))->days;

        if ($dateDiffInDays <= 5) {
            return $this->getRecentHistoricalExchangeRate($exchangeQuery);
        }

        return $this->getStatbankExchangeRate($exchangeQuery);
    }

    /** {@inheritdoc} */
    #[\Override]
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        $base = $exchangeQuery->getCurrencyPair()->getBaseCurrency();
        $quote = $exchangeQuery->getCurrencyPair()->getQuoteCurrency();

        return ('DKK' === $base && \in_array($quote, self::SUPPORTED_CURRENCIES))
            || ('DKK' === $quote && \in_array($base, self::SUPPORTED_CURRENCIES));
    }

    /** {@inheritdoc} */
    #[\Override]
    public function getName(): string
    {
        return 'national_bank_of_denmark';
    }

    /**
     * Gets a rate from the last five business days feed.
     *
     * @throws UnsupportedDateException
     * @throws UnsupportedCurrencyPairException
     */
    private function getRecentHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $content = $this->request(self::FIVE_DAY_HISTORY_URL);

        // remove BOM from beginning of content
        $content = substr($content, (int) strpos($content, '<'));

        $element = StringUtil::xmlToElement($content);
        $element->registerXPathNamespace('xmlns', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');

        $formattedDate = $exchangeQuery->getDate()->format('Y-m-d');
        $foreignCurrency = $this->getForeignCurrency($currencyPair);

        $elements = $element->xpath('//xmlns:Cube[@time="' . $formattedDate . '"]/xmlns:Cube[@currency="' . $foreignCurrency . '"]/@rate');

        if (empty($elements)) {
            if (empty($element->xpath('//xmlns:Cube[@time="' . $formattedDate . '"]'))) {
                throw new UnsupportedDateException($exchangeQuery->getDate(), $this);
            }

            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rateValue = $this->buildRateValue((float) $elements[0]['rate'], $currencyPair);

        return $this->createRate($currencyPair, $rateValue, $exchangeQuery->getDate());
    }

    /**
     * Gets a rate from the Statistics Denmark (statbank.dk) DNVALD table.
     *
     * @throws Exception
     * @throws UnsupportedDateException
     * @throws UnsupportedCurrencyPairException
     */
    private function getStatbankExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $date = $exchangeQuery->getDate();

        $body = json_encode([
            'table' => 'DNVALD',
            'format' => 'JSONSTAT',
            'lang' => 'en',
            'variables' => [
                ['code' => 'VALUTA', 'values' => [$this->getForeignCurrency($currencyPair)]],
                ['code' => 'KURTYP', 'values' => ['KBH']],
                ['code' => 'Tid', 'values' => [$date->format('Y\\Mm\\Dd')]],
            ],
        ]);

        $content = $this->postRequest(self::STATBANK_URL, (string) $body, ['Content-Type' => 'application/json']);
        $data = StringUtil::jsonToArray($content);

        if (isset($data['errorTypeCode'])) {
            $message = (string) ($data['message'] ?? '');

            if (str_contains($message, '(Tid)')) {
                throw new UnsupportedDateException($date, $this);
            }

            if (str_contains($message, '(VALUTA)')) {
                throw new UnsupportedCurrencyPairException($currencyPair, $this);
            }

            throw new Exception($message ?: 'Unknown statbank.dk error');
        }

        $rate = $data['dataset']['value'][0] ?? null;

        if (null === $rate) {
            throw new UnsupportedDateException($date, $this);
        }

        $rateValue = $this->buildRateValue((float) $rate, $currencyPair);

        return $this->createRate($currencyPair, $rateValue, $date);
    }

    /**
     * Gets the non-DKK currency of the pair.
     */
    private function getForeignCurrency(CurrencyPair $currencyPair): string
    {
        return 'DKK' === $currencyPair->getBaseCurrency()
            ? $currencyPair->getQuoteCurrency()
            : $currencyPair->getBaseCurrency();
    }

    /**
     * Converts a "DKK per 100 units" quotation to the rate of the pair.
     */
    private function buildRateValue(float $dkkPerHundredUnits, CurrencyPair $currencyPair): float
    {
        $rateValue = $dkkPerHundredUnits / 100.0;

        if ('DKK' === $currencyPair->getBaseCurrency()) {
            $rateValue = (float) number_format(1.0 / $rateValue, 6, '.', '');
        }

        return $rateValue;
    }
}
