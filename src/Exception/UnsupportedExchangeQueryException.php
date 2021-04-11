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

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\ExchangeRateService;

/**
 * Exception thrown when an exchange query is not supported by a service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class UnsupportedExchangeQueryException extends Exception
{
    /**
     * The query.
     *
     * @var ExchangeRateQuery
     */
    private $exchangeRateQuery;

    /**
     * The service.
     *
     * @var ExchangeRateService
     */
    private $service;

    /**
     * Constructor.
     *
     * @param ExchangeRateQuery   $exchangeRateQuery
     * @param ExchangeRateService $service
     */
    public function __construct(ExchangeRateQuery $exchangeRateQuery, ExchangeRateService $service)
    {
        parent::__construct(sprintf(
            'The exchange query "%s" is not supported by the service "%s".',
            $exchangeRateQuery->getCurrencyPair()->__toString(),
            \get_class($service)
        ));

        $this->exchangeRateQuery = $exchangeRateQuery;
        $this->service = $service;
    }

    /**
     * Gets the unsupported exchange query.
     *
     * @return ExchangeRateQuery
     */
    public function getExchangeRateQuery(): ExchangeRateQuery
    {
        return $this->exchangeRateQuery;
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
