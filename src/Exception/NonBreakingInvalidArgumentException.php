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
 * Generic InvalidArgumentException thrown by the library that doesn't break the chain.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class NonBreakingInvalidArgumentException extends \Exception
{
}
