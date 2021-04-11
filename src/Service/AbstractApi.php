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
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Abstract API Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class AbstractApi extends HttpService
{
    const API_KEY_OPTION = 'api_key';

    const LATEST_URL = 'https://currency.abstractapi.com/v1/latest?api_key=%s&base=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options[self::API_KEY_OPTION])) {
            throw new \InvalidArgumentException('The "api_key" option must be provided to use abstractapi.com');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeRateQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeRateQuery->getCurrencyPair();

        $url = sprintf(
            self::LATEST_URL,
            $this->options[self::API_KEY_OPTION],
            $currencyPair->getBaseCurrency()
        );

        $content = $this->request($url);
        $data = StringUtil::jsonToArray($content);

        if (isset($data['exchange_rate'][$currencyPair->getQuoteCurrency()])) {
            $date = new \DateTime(
                $data['last_updated_utc'],
                new \DateTimeZone('UTC')
            );

            $rate = $data['exchange_rate'][$currencyPair->getQuoteCurrency()];

            return $this->createRate($currencyPair, (float) $rate, $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return !$exchangeQuery instanceof HistoricalExchangeRateQuery;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'abstract_api';
    }
}
