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

use Exchanger\Contract\ExchangeRateService;
use Exchanger\ExchangeRate;
use Exchanger\Contract\CurrencyPair as CurrencyPairContract;

/**
 * Base class for exchanger services.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
abstract class Service implements ExchangeRateService
{
    /**
     * The options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->processOptions($options);
        $this->options = $options;
    }

    /**
     * Processes the service options.
     *
     * @param array &$options
     */
    public function processOptions(array &$options): void
    {
    }

    /**
     * Creates an instant rate.
     *
     * @param CurrencyPairContract $currencyPair
     * @param float                $rate
     * @param \DateTimeInterface   $date
     *
     * @return ExchangeRate
     */
    protected function createRate(CurrencyPairContract $currencyPair, float $rate, \DateTimeInterface $date): ExchangeRate
    {
        return new ExchangeRate($currencyPair, $rate, $date, $this->getName());
    }

    /**
     * Creates an instant rate.
     *
     * @param CurrencyPairContract $currencyPair
     * @param float                $rate
     *
     * @return ExchangeRate
     */
    protected function createInstantRate(CurrencyPairContract $currencyPair, float $rate): ExchangeRate
    {
        return new ExchangeRate($currencyPair, $rate, new \DateTime(), $this->getName());
    }
}
