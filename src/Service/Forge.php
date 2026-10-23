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
use Exchanger\StringUtil;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Forge Service.
 *
 * @author Alberto Diaz
 */
final class Forge extends HttpService
{
    public const URL = 'https://api.1forge.com/quotes?pairs=%s&api_key=%s';

    /** {@inheritdoc} */
    #[\Override]
    public function processOptions(array &$options): void
    {
        if (!isset($options['api_key'])) {
            throw new \InvalidArgumentException('The "api_key" option must be provided.');
        }
    }

    /** {@inheritdoc} */
    #[\Override]
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return !$exchangeQuery instanceof HistoricalExchangeRateQuery;
    }

    /**
     * {@inheritdoc}
     *
     * Renaming $exchangeRateQuery to match the interface would break callers using named arguments; do it in 3.0.
     *
     * @psalm-suppress ParamNameMismatch
     */
    #[\Override]
    public function getExchangeRate(ExchangeRateQuery $exchangeRateQuery): ExchangeRateContract
    {
        $currencyPair = $exchangeRateQuery->getCurrencyPair();
        $currencySymbol = $currencyPair->getBaseCurrency() . '/' . $currencyPair->getQuoteCurrency();
        $url = sprintf(self::URL, $currencySymbol, $this->options['api_key']);

        $content = $this->request($url);
        $data = StringUtil::jsonToArray($content);

        $result = reset($data);

        if (\is_array($result) && [] !== $result) {
            $date = new \DateTime();

            if (null !== $result['t']) {
                $date->setTimestamp($result['t']);
            }

            if ($result['s'] == $currencySymbol) {
                return $this->createRate($currencyPair, (float) ($result['p']), $date);
            }
        }

        throw new UnsupportedCurrencyPairException($currencyPair, $this);
    }

    /** {@inheritdoc} */
    #[\Override]
    public function getName(): string
    {
        return 'forge';
    }
}
