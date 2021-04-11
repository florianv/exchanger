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

/**
 * Exception thrown by the Chain Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class ChainException extends Exception
{
    /**
     * The exceptions.
     *
     * @var Exception[]
     */
    private $exceptions;

    /**
     * Creates a new chain exception.
     *
     * @param Exception[] $exceptions
     */
    public function __construct(array $exceptions)
    {
        $messages = array_map(function (\Throwable $exception) {
            return sprintf(
                '%s: %s',
                \get_class($exception),
                $exception->getMessage()
            );
        }, $exceptions);

        parent::__construct(
            sprintf(
                "The chain resulted in %d exception(s):\r\n%s",
                \count($exceptions),
                implode("\r\n", $messages)
            )
        );

        $this->exceptions = $exceptions;
    }

    /**
     * Gets the exceptions indexed by service class name.
     *
     * @return Exception[]
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }
}
