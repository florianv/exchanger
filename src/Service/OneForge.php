<?php

    /*
     * This file is part of Exchanger.
     *
     * (c) Florian Voutzinos <florian@voutzinos.com>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */

    namespace Exchanger\Service;

    use Exchanger\Exception\UnsupportedCurrencyPairException;
    use Exchanger\ExchangeRate;
    use Exchanger\Contract\ExchangeRateQuery;
    use Exchanger\Contract\HistoricalExchangeRateQuery;

    /**
     * OneForge (1forge) Service.
     *
     * @author Jacob Davis <contact@1forge.com>
     */
    class OneForge extends Service
    {
        const URL = 'http://1forge.com/forex-data-api/1.0.1/quotes?pairs=%s%s';

        /**
         * {@inheritdoc}
         */
        public function getExchangeRate(ExchangeRateQuery $exchangeQuery)
        {
            $baseCurrency  = $exchangeQuery->getCurrencyPair()->getBaseCurrency();
            $quoteCurrency = $exchangeQuery->getCurrencyPair()->getQuoteCurrency();

            $url     = sprintf(self::URL, $baseCurrency, $quoteCurrency);
            $content = $this->request($url);
            $content = json_decode($content);

            if (count($content) <= 0)
            {
                throw new UnsupportedCurrencyPairException($exchangeQuery->getCurrencyPair(), $this);
            }

            $quote = $content[0];

            return new ExchangeRate($quote->price, new \DateTime());
        }

        /**
         * {@inheritdoc}
         */
        public function supportQuery(ExchangeRateQuery $exchangeQuery)
        {
            return !$exchangeQuery instanceof HistoricalExchangeRateQuery;
        }
    }