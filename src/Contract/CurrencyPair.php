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
 * Represents a currency pair.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
interface CurrencyPair
{
    /**
     * Gets the base currency.
     *
     * @return string
     */
    public function getBaseCurrency(): string;

    /**
     * Gets the quote currency.
     *
     * @return string
     */
    public function getQuoteCurrency(): string;

    /**
     * Checks if the pair is identical.
     *
     * @return bool
     */
    public function isIdentical(): bool;

    /**
     * Returns a string representation of the pair.
     *
     * @return string
     */
    public function __toString(): string;
}
