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
use Exchanger\Exception\Exception;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Xignite Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class Xignite extends HttpService
{
    use SupportsHistoricalQueries;

    const LATEST_URL = 'https://globalcurrencies.xignite.com/xGlobalCurrencies.json/GetRealTimeRates?Symbols=%s&_fields=Outcome,Message,Symbol,Date,Time,Bid&_Token=%s';

    const HISTORICAL_URL = 'http://globalcurrencies.xignite.com/xGlobalCurrencies.json/GetHistoricalRates?Symbols=%s&AsOfDate=%s&_Token=%s&FixingTime=&PriceType=Mid';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options['token'])) {
            throw new \InvalidArgumentException('The "token" option must be provided.');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $url = sprintf(
            self::LATEST_URL,
            $currencyPair->getBaseCurrency().$currencyPair->getQuoteCurrency(),
            $this->options['token']
        );

        $content = $this->request($url);

        $json = StringUtil::jsonToArray($content);
        $data = $json[0];

        if ('Success' !== $data['Outcome']) {
            throw new Exception($data['Message']);
        }

        $dateString = $data['Date'].' '.$data['Time'];

        if (!$date = \DateTime::createFromFormat('m/d/Y H:i:s A', $dateString, new \DateTimeZone('UTC'))) {
            throw new UnsupportedCurrencyPairException($currencyPair, $this);
        }

        return $this->createRate($currencyPair, (float) ($data['Bid']), $date);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $queryDate = $exchangeQuery->getDate();
        $symbol = $currencyPair->getBaseCurrency().$currencyPair->getQuoteCurrency();

        $url = sprintf(
            self::HISTORICAL_URL,
            $symbol,
            $queryDate->format('m/d/Y'),
            $this->options['token']
        );

        $content = $this->request($url);

        $json = StringUtil::jsonToArray($content);
        $data = $json[0];

        if ('Success' !== $data['Outcome']) {
            throw new Exception($data['Message']);
        }

        if (!$date = \DateTime::createFromFormat('m/d/Y', $data['StartDate'], new \DateTimeZone('UTC'))) {
            throw new UnsupportedDateException($queryDate, $this);
        }

        return $this->createRate($currencyPair, (float) ($data['Average']), $date);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'xignite';
    }
}
