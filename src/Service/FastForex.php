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
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * fastFOREX.io Service.
 *
 * @author Tom <tom@whamsoftware.com>
 */
final class FastForex extends HttpService
{
    const FETCH_ONE_URL = 'https://api.fastforex.io/fetch-one?from=%s&to=%s&api_key=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options['api_key'])) {
            throw new \InvalidArgumentException('The "api_key" option must be provided.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return !$exchangeQuery instanceof \Exchanger\HistoricalExchangeRateQuery;
    }

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeRateQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeRateQuery->getCurrencyPair();
        $baseCurrency = $currencyPair->getBaseCurrency();
        $quoteCurrency = $currencyPair->getQuoteCurrency();
        $url = sprintf(self::FETCH_ONE_URL, $baseCurrency, $quoteCurrency, $this->options['api_key']);

        $content = $this->request($url);
        $result = StringUtil::jsonToArray($content);

        if (!isset($result['error'])) {
            try {
                $date = new \DateTime($result['updated']);
            } catch (\Throwable $thrown) {
                $date = new \DateTime();
            }
            if (isset($result['base']) && $result['base'] == $baseCurrency) {
                if (isset($result['result'][$quoteCurrency])) {
                    return $this->createRate($currencyPair, (float) ($result['result'][$quoteCurrency]), $date);
                }
            }
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'fastforex';
    }
}
