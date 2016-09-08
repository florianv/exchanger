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
 * Represents an exchange rate.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
interface ExchangeRate
{
    /**
     * Gets the rate value.
     *
     * @return string
     */
    public function getValue();

    /**
     * Gets the date at which this rate was calculated.
     *
     * @return \Datetime
     */
    public function getDate();
}
