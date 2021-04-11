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

namespace Exchanger;

use Exchanger\Contract\CurrencyPair as CurrencyPairContract;
use Exchanger\Contract\ExchangeRateQuery as ExchangeRateQueryContract;

/**
 * Default exchange query implementation.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class ExchangeRateQuery implements ExchangeRateQueryContract
{
    /**
     * The currency pair.
     *
     * @var CurrencyPairContract
     */
    private $currencyPair;

    /**
     * The options.
     *
     * @var array
     */
    private $options;

    /**
     * Creates a new query.
     *
     * @param CurrencyPairContract $currencyPair
     * @param array                $options
     */
    public function __construct(CurrencyPairContract $currencyPair, array $options = [])
    {
        $this->currencyPair = $currencyPair;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencyPair(): CurrencyPairContract
    {
        return $this->currencyPair;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name, $default = null)
    {
        if (\array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        return $default;
    }
}
