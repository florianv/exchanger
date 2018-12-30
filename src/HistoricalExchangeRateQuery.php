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
use Exchanger\Contract\HistoricalExchangeRateQuery as HistoricalExchangeQueryContract;

/**
 * Default historical exchange query implementation.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class HistoricalExchangeRateQuery extends ExchangeRateQuery implements HistoricalExchangeQueryContract
{
    /**
     * The date.
     *
     * @var \DateTimeInterface
     */
    private $date;

    /**
     * Creates a new query.
     *
     * @param CurrencyPairContract $currencyPair
     * @param \DateTimeInterface   $date
     * @param array                $options
     */
    public function __construct(CurrencyPairContract $currencyPair, \DateTimeInterface $date, array $options = [])
    {
        parent::__construct($currencyPair, $options);

        $this->date = $date instanceof \DateTime ? clone $date : $date;
    }

    /**
     * {@inheritdoc}
     */
    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }
}
