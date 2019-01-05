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

namespace Exchanger\Contract;

/**
 * Represents an exchange rate.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
interface ExchangeRate
{
    /**
     * Gets the rate value.
     *
     * @return float
     */
    public function getValue(): float;

    /**
     * Gets the date at which this rate was calculated.
     *
     * @return \DateTimeInterface
     */
    public function getDate(): \DateTimeInterface;

    /**
     * Gets the name of the provider that returned this rate.
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Gets the currency pair.
     *
     * @return CurrencyPair
     */
    public function getCurrencyPair(): CurrencyPair;
}
