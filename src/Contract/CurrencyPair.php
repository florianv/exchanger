<?php

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
    public function getBaseCurrency();

    /**
     * Gets the quote currency.
     *
     * @return string
     */
    public function getQuoteCurrency();

    /**
     * Checks if the pair is identical.
     *
     * @return bool
     */
    public function isIdentical();

    /**
     * Returns a string representation of the pair.
     *
     * @return string
     */
    public function __toString();
}
