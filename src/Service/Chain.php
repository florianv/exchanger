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

use Exchanger\Contract\ExchangeRate;
use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\ExchangeRateService;
use Exchanger\Exception\ChainException;

/**
 * A service using other services in a chain.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class Chain implements ExchangeRateService
{
    /**
     * The services.
     *
     * @var array|ExchangeRateService[]
     */
    private $services;

    /**
     * Creates a new chain service.
     *
     * @param ExchangeRateService[] $services
     */
    public function __construct(iterable $services = [])
    {
        if (!\is_array($services)) {
            /** @var \Iterator $services */
            $services = iterator_to_array($services);
        }

        $this->services = $services;
    }

    /**
     * {@inheritdoc}
     */
    public function getExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRate
    {
        $exceptions = [];

        foreach ($this->services as $service) {
            if (!$service->supportQuery($exchangeQuery)) {
                continue;
            }

            try {
                return $service->getExchangeRate($exchangeQuery);
            } catch (\Throwable $e) {
                $exceptions[] = $e;
            }
        }

        throw new ChainException($exceptions);
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        foreach ($this->services as $service) {
            if ($service->supportQuery($exchangeQuery)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'chain';
    }
}
