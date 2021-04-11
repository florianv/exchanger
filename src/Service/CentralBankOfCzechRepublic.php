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

use DateTimeImmutable;
use DateTimeInterface;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Central Bank of Czech Republic (CNB) Service.
 *
 * @author Petr Kramar <petr.kramar@perlur.cz>
 */
final class CentralBankOfCzechRepublic extends HttpService
{
    use SupportsHistoricalQueries;

    const URL = 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt';

    const DATE_FORMAT = 'd.m.Y';

    const DATE_QUERY_PARAMETER_NAME = 'date';

    const CURRENCY_LINE_PATTERN = '#^.*\|.*\|\d+\|\w{3}\|\d+(?:,\d+)?$#';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        return $this->doCreateRate($exchangeQuery, new DateTimeImmutable());
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        return $this->doCreateRate($exchangeQuery, $exchangeQuery->getDate());
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return 'CZK' === $exchangeQuery->getCurrencyPair()->getQuoteCurrency();
    }

    /**
     * Creates the rate.
     *
     * @param ExchangeRateQuery $exchangeQuery
     * @param DateTimeInterface $requestedDate
     *
     * @return ExchangeRate
     *
     * @throws UnsupportedCurrencyPairException
     */
    private function doCreateRate(ExchangeRateQuery $exchangeQuery, DateTimeInterface $requestedDate): ExchangeRate
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $content = $this->request($this->buildUrl($requestedDate));

        $lines = explode("\n", $content);

        if (!$date = \DateTime::createFromFormat(self::DATE_FORMAT, $this->parseDate($lines[0]))) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        $date->setTime(0, 0, 0);
        foreach (\array_slice($lines, 2) as $line) {
            if (!preg_match(self::CURRENCY_LINE_PATTERN, $line)) {
                continue;
            }
            list(, , $count, $code, $rate) = explode('|', $line);

            if ($code === $currencyPair->getBaseCurrency()) {
                $rate = (float) str_replace(',', '.', $rate);

                return $this->createRate($currencyPair, (float) ($rate / (int) $count), $date);
            }
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * Parses the date.
     *
     * @param string $line First line of fetched response
     *
     * @return string The date
     */
    private function parseDate(string $line): string
    {
        $words = preg_split('/[\s]+/', $line);

        return $words[0];
    }

    /**
     * @param DateTimeInterface $requestedDate
     *
     * @return string
     */
    private function buildUrl(DateTimeInterface $requestedDate): string
    {
        return self::URL.'?'.http_build_query([self::DATE_QUERY_PARAMETER_NAME => $requestedDate->format(self::DATE_FORMAT)]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'central_bank_of_czech_republic';
    }
}
