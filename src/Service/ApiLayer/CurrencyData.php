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

namespace Exchanger\Service\ApiLayer;

use Exchanger\Contract\CurrencyPair;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\Exception;
use Exchanger\Exception\NonBreakingInvalidArgumentException;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRate;
use Exchanger\Service\HttpService;
use Exchanger\Service\SupportsHistoricalQueries;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * ApiLayer Currency Data Service.
 *
 * @see https://apilayer.com/marketplace/currency_data-api
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class CurrencyData extends HttpService
{
    use SupportsHistoricalQueries;

    const API_KEY_OPTION = 'api_key';

    const LATEST_URL = 'https://api.apilayer.com/currency_data/live?apikey=%s&currencies=%s';

    const HISTORICAL_URL = 'https://api.apilayer.com/currency_data/historical?apikey=%s&date=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options[self::API_KEY_OPTION])) {
            throw new NonBreakingInvalidArgumentException('The "api_key" option must be provided to use CurrencyData (https://apilayer.com/marketplace/currency_data-api).');
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
            $this->options[self::API_KEY_OPTION],
            $currencyPair->getQuoteCurrency()
        );

        return $this->doCreateRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $url = sprintf(
            self::HISTORICAL_URL,
            $this->options[self::API_KEY_OPTION],
            $exchangeQuery->getDate()->format('Y-m-d')
        );

        return $this->doCreateRate($url, $exchangeQuery->getCurrencyPair());
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return true;
    }

    /**
     * Creates a rate.
     *
     * @param string       $url
     * @param CurrencyPair $currencyPair
     *
     * @return ExchangeRate|null
     *
     * @throws Exception
     */
    private function doCreateRate($url, CurrencyPair $currencyPair): ExchangeRate
    {
        $content = $this->request($url);
        $data = StringUtil::jsonToArray($content);

        if (empty($data['success'])) {
            throw new Exception($data['error']['info']);
        }

        $date = (new \DateTime())->setTimestamp($data['timestamp']);
        $hash = $currencyPair->getBaseCurrency().$currencyPair->getQuoteCurrency();

        if ($data['source'] === $currencyPair->getBaseCurrency() && isset($data['quotes'][$hash])) {
            return $this->createRate($currencyPair, (float) ($data['quotes'][$hash]), $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'apilayer_currency_data';
    }
}
