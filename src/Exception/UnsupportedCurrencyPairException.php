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

namespace Exchanger\Exception;

use Exchanger\Contract\CurrencyPair;
use Exchanger\Contract\ExchangeRateService;

/**
 * Exception thrown when a currency pair is not supported by a service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class UnsupportedCurrencyPairException extends Exception
{
    /**
     * The currency pair.
     *
     * @var CurrencyPair
     */
    private $currencyPair;

    /**
     * The service.
     *
     * @var ExchangeRateService
     */
    private $service;

    /**
     * Constructor.
     *
     * @param CurrencyPair        $currencyPair
     * @param ExchangeRateService $service
     */
    public function __construct(CurrencyPair $currencyPair, ExchangeRateService $service)
    {
        parent::__construct(
            sprintf(
                'The currency pair "%s" is not supported by the service "%s".',
                $currencyPair->__toString(),
                \get_class($service)
            )
        );

        $this->currencyPair = $currencyPair;
        $this->service = $service;
    }

    /**
     * Gets the unsupported currency pair.
     *
     * @return CurrencyPair
     */
    public function getCurrencyPair(): CurrencyPair
    {
        return $this->currencyPair;
    }

    /**
     * Gets the service.
     *
     * @return ExchangeRateService
     */
    public function getService(): ExchangeRateService
    {
        return $this->service;
    }
}
