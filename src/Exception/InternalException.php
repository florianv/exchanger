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
 * For internal exceptions only that are not caught by the Chain Service.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class InternalException extends Exception
{
}
