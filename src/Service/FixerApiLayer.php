<?php

declare(strict_types=1);

namespace Exchanger\Service;

use Exchanger\Contract\CurrencyPair;
use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\StringUtil;
use Exchanger\Exception\Exception;

final class FixerApiLayer extends HttpService
{
    use SupportsHistoricalQueries;

    private const API_KEY_OPTION = 'api_key';

    private const LATEST_URL = 'https://api.apilayer.com/fixer/latest?base=%s';

    private const HISTORICAL_URL = 'https://api.apilayer.com/fixer/%s?base=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options[self::API_KEY_OPTION])) {
            throw new \InvalidArgumentException('The "api_key" option must be provided to use fixer.io');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $url = \sprintf(
            self::LATEST_URL,
            $currencyPair->getBaseCurrency()
        );

        return $this->doCreateRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        $url = \sprintf(
            self::HISTORICAL_URL,
            $exchangeQuery->getDate()->format('Y-m-d'),
            $currencyPair->getBaseCurrency()
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
     * @return ExchangeRate
     *
     * @throws Exception
     */
    private function doCreateRate(string $url, CurrencyPair $currencyPair): ExchangeRate
    {
        $content = $this->request($url, [
            'apikey' => $this->options[self::API_KEY_OPTION]
        ]);
        $data = StringUtil::jsonToArray($content);

        if (isset($data['error'])) {
            throw new Exception($this->getErrorMessage($data['error']['code']));
        }

        if (isset($data['rates'][$currencyPair->getQuoteCurrency()])) {
            $date = new \DateTime($data['date']);
            $rate = $data['rates'][$currencyPair->getQuoteCurrency()];

            return $this->createRate($currencyPair, (float) $rate, $date);
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /**
     * Gets the error message corresponding to the error code.
     *
     * @param int $code The error code
     *
     * @return string
     */
    private function getErrorMessage(int $code): string
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

        return $errors[$code] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'fixer_apilayer';
    }
}
