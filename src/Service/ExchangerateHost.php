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
 * Exchangerate Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class ExchangerateHost extends HttpService
{
    use SupportsHistoricalQueries;

    const LATEST_URL = 'https://api.exchangerate.host/latest?base=%s&v=%s';

    const HISTORICAL_URL = 'https://api.exchangerate.host/%s?base=%s';
    const OPTION_PLACES = 'places';
    const OPTION_SOURCE = 'source';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

		$url = sprintf(
			self::LATEST_URL,
			$currencyPair->getBaseCurrency(),
            date('Y-m-d')
		);

        return $this->doCreateRate($this->additionalQueryParameters($url, $exchangeQuery), $currencyPair);
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
			$exchangeQuery->getCurrencyPair()->getBaseCurrency()
		);

        return $this->doCreateRate($this->additionalQueryParameters($url, $exchangeQuery), $currencyPair);
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
    private function doCreateRate($url, CurrencyPair $currencyPair): ExchangeRate
    {
        $content = $this->request($url);
        $data = StringUtil::jsonToArray($content);

        if (false === $data['success']) {
            throw new Exception();
        }

        if (isset($data['rates'][$currencyPair->getQuoteCurrency()])) {
            $date = new \DateTime($data['date']);
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
        return 'exchangeratehost';
    }

    private function additionalQueryParameters(string $url, $exchangeRateQuery): string
    {
        if (isset($this->options[self::OPTION_PLACES])) {
            $places = $this->options[self::OPTION_PLACES];
        }

        if ($exchangeRateQuery->getOption(self::OPTION_PLACES)) {
            $places = $exchangeRateQuery->getOption(self::OPTION_PLACES);
        }

        if (isset($this->options[self::OPTION_SOURCE])) {
            $source = $this->options[self::OPTION_SOURCE];
        }

        if ($exchangeRateQuery->getOption(self::OPTION_SOURCE)) {
            $source = $exchangeRateQuery->getOption(self::OPTION_SOURCE);
        }

        if (isset($places)) {
            $url .= '&places=' . $places;
        }

        if (isset($source)) {
            $url .= '&source='. $source;
        }

        return $url;
    }
}
