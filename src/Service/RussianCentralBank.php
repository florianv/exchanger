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
 * Russian Central Bank Service.
 *
 * @author Maksim Platonov
 */
final class RussianCentralBank extends HttpService
{
    use SupportsHistoricalQueries;

    const URL = 'http://www.cbr.ru/scripts/XML_daily.asp';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $baseCurrency = $currencyPair->getBaseCurrency();

        $content = $this->request(self::URL);
        $element = StringUtil::xmlToElement($content);

        $elements = $element->xpath('./Valute[CharCode="'.$baseCurrency.'"]');
        $date = \DateTime::createFromFormat('!d.m.Y', (string) $element['Date']);

        if (empty($elements) || !$date) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rate = str_replace(',', '.', (string) $elements['0']->Value);
        $nominal = str_replace(',', '.', (string) $elements['0']->Nominal);

        return $this->createRate($currencyPair, (float) $rate / $nominal, $date);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $baseCurrency = $currencyPair->getBaseCurrency();
        $date = $exchangeQuery->getDate();
        $formattedDate = $date->format('d.m.Y');

        $content = $this->request(self::URL.'?'.http_build_query(['date_req' => $formattedDate]));
        $element = StringUtil::xmlToElement($content);

        $elements = $element->xpath('./Valute[CharCode="'.$baseCurrency.'"]');

        if (empty($elements)) {
            if ((string) $element['Date'] !== $date->format('d.m.Y')) {
                throw new UnsupportedDateException($date, $this);
            }

            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $rate = str_replace(',', '.', (string) $elements['0']->Value);
        $nominal = str_replace(',', '.', (string) $elements['0']->Nominal);

        return $this->createRate($currencyPair, (float) ($rate / $nominal), $date);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return 'RUB' === $exchangeQuery->getCurrencyPair()->getQuoteCurrency();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'russian_central_bank';
    }
}
