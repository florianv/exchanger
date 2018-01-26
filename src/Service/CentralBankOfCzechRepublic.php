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

/**
 * Central Bank of Czech Republic (CNB) Service.
 *
 * @author Petr Kramar <petr.kramar@perlur.cz>
 */
class CentralBankOfCzechRepublic extends Service
{
    const URL = 'http://www.cnb.cz/cs/financni_trhy/devizovy_trh/kurzy_devizoveho_trhu/denni_kurz.txt';

    const DATE_FORMAT = 'd.m.Y';

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery)
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $content = $this->request(self::URL);

        $lines = explode("\n", $content);

        if (!$date = \DateTime::createFromFormat(self::DATE_FORMAT, $this->parseDate($lines[0]))) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $date->setTime(0, 0, 0);

        foreach (array_slice($lines, 2) as $currency) {
            list(, , $count, $code, $rate) = explode('|', $currency);

            if ($code === $currencyPair->getBaseCurrency()) {
                $rate = (float) str_replace(',', '.', $rate);

                return new ExchangeRate((string) ($rate / (int) $count), $date);
            }
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery)
    {
        return !$exchangeQuery instanceof HistoricalExchangeRateQuery
        && 'CZK' === $exchangeQuery->getCurrencyPair()->getQuoteCurrency();
    }

    /**
     * Parses the date.
     *
     * @param string $line First line of fetched response
     *
     * @return string The date
     */
    private function parseDate($line)
    {
        $words = preg_split('/[\s]+/', $line);

        return $words[0];
    }
}
