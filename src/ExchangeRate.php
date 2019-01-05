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

use Exchanger\Contract\ExchangeRate as ExchangeRateContract;
use Exchanger\Contract\CurrencyPair as CurrencyPairContract;

/**
 * Represents a rate.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class ExchangeRate implements ExchangeRateContract
{
    /**
     * The currency pair.
     *
     * @var CurrencyPairContract
     */
    private $currencyPair;

    /**
     * The value.
     *
     * @var float
     */
    private $value;

    /**
     * The date.
     *
     * @var \DateTimeInterface
     */
    private $date;

    /**
     * The provider.
     *
     * @var string
     */
    private $provider;

    /**
     * Creates a new rate.
     *
     * @param CurrencyPairContract $currencyPair The currency pair
     * @param float                $value        The rate value
     * @param \DateTimeInterface   $date         The date at which this rate was calculated
     * @param string               $provider     The class name of the provider that returned this rate
     */
    public function __construct(CurrencyPairContract $currencyPair, float $value, \DateTimeInterface $date, string $provider)
    {
        $this->currencyPair = $currencyPair;
        $this->value = $value;
        $this->date = $date;
        $this->provider = $provider;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderName(): string
    {
        return $this->provider;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencyPair(): CurrencyPairContract
    {
        return $this->currencyPair;
    }
}
