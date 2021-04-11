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
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * XchangeApi Service.
 *
 * @author xChangeAPI.com <hello@xchangeapi.com>
 */
final class XchangeApi extends HttpService
{
    use SupportsHistoricalQueries;

    const API_KEY_OPTION = 'api-key';

    const LATEST_URL = 'https://api.xchangeapi.com/latest?base=%s&api-key=%s';

    const HISTORICAL_URL = 'https://api.xchangeapi.com/historical/%s?api-key=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options[self::API_KEY_OPTION])) {
            throw new \InvalidArgumentException('The "api-key" option must be provided to use xChangeApi.com');
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
            $currencyPair->getBaseCurrency(),
            $this->options[self::API_KEY_OPTION]
        );

        return $this->doCreateRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();
        $url = sprintf(
            self::HISTORICAL_URL,
            $exchangeQuery->getDate()->format('Y-m-d'),
            $this->options[self::API_KEY_OPTION]
        );

        return $this->doCreateRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        return \in_array($currencyPair->getBaseCurrency(), $this->getSupportedCodes());
    }

    /**
     * Array of codes supported according to.
     *
     * @url https://xchangeapi.com/currencies
     */
    private function getSupportedCodes(): array
    {
        return require __DIR__.'/resources/xchangeapi-codes.php';
    }

    /**
     * Creates a rate.
     *
     * @param string $url
     */
    private function doCreateRate($url, CurrencyPair $currencyPair): ExchangeRate
    {
        $content = $this->request($url);
        $data = StringUtil::jsonToArray($content);

        if (isset($data['message'])) {
            throw new Exception($data['message']);
        }

        if (isset($data['rates'][$currencyPair->getQuoteCurrency()])) {
            $date = \DateTime::createFromFormat('U', (string) $data['timestamp']);
            $rate = $data['rates'][$currencyPair->getQuoteCurrency()];

            return $this->createRate($currencyPair, (float) $rate, $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'xchangeapi';
    }
}
