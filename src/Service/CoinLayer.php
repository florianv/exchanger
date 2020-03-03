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
 * Coin Layer Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class CoinLayer extends HttpService
{
    use SupportsHistoricalQueries;

    const LATEST_URL = '%s://api.coinlayer.com/api/live?access_key=%s&symbols=%s&target=%s';

    const HISTORICAL_URL = '%s://api.coinlayer.com/api/%s?access_key=%s&symbols=%s&target=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options['access_key'])) {
            throw new \InvalidArgumentException('The "access_key" option must be provided.');
        }

        if (!isset($options['paid'])) {
            $options['paid'] = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $protocol = $this->options['paid'] ? 'https' : 'http';

        $url = sprintf(
            self::LATEST_URL,
            $protocol,
            $this->options['access_key'],
            $currencyPair->getBaseCurrency(),
            $currencyPair->getQuoteCurrency()
        );

        return $this->doCreateRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $protocol = $this->options['paid'] ? 'https' : 'http';

        $url = sprintf(
            self::HISTORICAL_URL,
            $protocol,
            $exchangeQuery->getDate()->format('Y-m-d'),
            $this->options['access_key'],
            $currencyPair->getBaseCurrency(),
            $currencyPair->getQuoteCurrency()
        );

        return $this->doCreateRate($url, $currencyPair);
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
            throw new Exception($this->getErrorMessage($data['error']['code']));
        }

        $date = (new \DateTime())->setTimestamp($data['timestamp']);
        $quote = $currencyPair->getBaseCurrency();

        if ($data['target'] === $currencyPair->getQuoteCurrency() && isset($data['rates'][$quote])) {
            return $this->createRate($currencyPair, (float) ($data['rates'][$quote]), $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'coin_layer';
    }

    /**
     * Gets the error message corresponding to the error code.
     *
     * @param string $code The error code
     *
     * @return string
     */
    private function getErrorMessage($code): string
    {
        $errors = [
            404 => 'The requested resource does not exist.',
            101 => 'No API Key was specified or an invalid API Key was specified.',
            103 => 'The requested API endpoint does not exist.',
            104 => 'The maximum allowed API amount of monthly API requests has been reached.',
            105 => 'The current subscription plan does not support this API endpoint.',
            106 => 'The current request did not return any results.',
            102 => 'The account this API request is coming from is inactive.',
            201 => 'An invalid base currency has been entered.',
            202 => 'One or more invalid symbols have been specified.',
            301 => 'No date has been specified.',
            302 => 'An invalid date has been specified.',
            403 => 'No or an invalid amount has been specified.',
            501 => 'No or an invalid timeframe has been specified.',
            502 => 'No or an invalid "start_date" has been specified.',
            503 => 'No or an invalid "end_date" has been specified.',
            504 => 'An invalid timeframe has been specified.',
            505 => 'The specified timeframe is too long, exceeding 365 days.',
        ];

        return isset($errors[$code]) ? $errors[$code] : '';
    }
}
