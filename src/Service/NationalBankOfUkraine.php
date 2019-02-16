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
 * National Bank of Ukraine.
 *
 * @author Ilya Zelenin <ilya.zelenin@make.im>
 */
class NationalBankOfUkraine extends HttpService
{
    use SupportsHistoricalQueries;

    protected const URL = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange';

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedCurrencyPairException
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $baseCurrency = $currencyPair->getBaseCurrency();

        $content = $this->request(self::URL);
        $element = StringUtil::xmlToElement($content);

        $elements = $element->xpath('./currency[cc="'.$baseCurrency.'"]');

        if (empty($elements)) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $item = $elements[0];
        $date = \DateTime::createFromFormat('!d.m.Y', (string) $item->exchangedate);

        if (!$date) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rate = (float) str_replace(',', '.', (string) $item->rate);

        return $this->createRate($currencyPair, $rate, $date);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedDateException
     * @throws UnsupportedCurrencyPairException
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $baseCurrency = $currencyPair->getBaseCurrency();
        $date = $exchangeQuery->getDate();
        $formattedDate = $date->format('Ymd');

        $content = $this->request(self::URL.'?'.http_build_query(['date' => $formattedDate]));
        $element = StringUtil::xmlToElement($content);

        $elements = $element->xpath('./currency[cc="'.$baseCurrency.'"]');

        if (empty($elements)) {
            if ($element->xpath('./error')) {
                throw new UnsupportedDateException($date, $this);
            }

            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $item = $elements[0];

        $rate = (float) str_replace(',', '.', (string) $item->rate);

        return $this->createRate($currencyPair, $rate, $date);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return 'UAH' === $exchangeQuery->getCurrencyPair()->getQuoteCurrency();
    }

    /**
     * Gets the name of the exchange rate service.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'national_bank_of_ukraine';
    }
}
