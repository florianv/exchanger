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
 * Currency Layer Service.
 *
 * @author Pascal Hofmann <mail@pascalhofmann.de>
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class CurrencyLayer extends HttpService
{
    use SupportsHistoricalQueries;

    const FREE_LATEST_URL = 'http://www.apilayer.net/api/live?access_key=%s&currencies=%s';

    const ENTERPRISE_LATEST_URL = 'https://www.apilayer.net/api/live?access_key=%s&source=%s&currencies=%s';

    const FREE_HISTORICAL_URL = 'http://apilayer.net/api/historical?access_key=%s&date=%s';

    const ENTERPRISE_HISTORICAL_URL = 'https://apilayer.net/api/historical?access_key=%s&date=%s&source=%s';

    /**
     * {@inheritdoc}
     */
    public function processOptions(array &$options): void
    {
        if (!isset($options['access_key'])) {
            throw new \InvalidArgumentException('The "access_key" option must be provided.');
        }

        if (!isset($options['enterprise'])) {
            $options['enterprise'] = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeQuery->getCurrencyPair();

        if ($this->options['enterprise']) {
            $url = sprintf(
                self::ENTERPRISE_LATEST_URL,
                $this->options['access_key'],
                $currencyPair->getBaseCurrency(),
                $currencyPair->getQuoteCurrency()
            );
        } else {
            $url = sprintf(
                self::FREE_LATEST_URL,
                $this->options['access_key'],
                $currencyPair->getQuoteCurrency()
            );
        }

        return $this->doCreateRate($url, $currencyPair);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        if ($this->options['enterprise']) {
            $url = sprintf(
                self::ENTERPRISE_HISTORICAL_URL,
                $this->options['access_key'],
                $exchangeQuery->getDate()->format('Y-m-d'),
                $exchangeQuery->getCurrencyPair()->getBaseCurrency()
            );
        } else {
            $url = sprintf(
                self::FREE_HISTORICAL_URL,
                $this->options['access_key'],
                $exchangeQuery->getDate()->format('Y-m-d')
            );
        }

        return $this->doCreateRate($url, $exchangeQuery->getCurrencyPair());
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return $this->options['enterprise'] || 'USD' === $exchangeQuery->getCurrencyPair()->getBaseCurrency();
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
        return 'currency_layer';
    }
}
