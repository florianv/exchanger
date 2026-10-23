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

namespace Exchanger\Tests;

/**
 * Reads fixtures and other files in tests, failing loudly instead of returning false.
 */
final class FileContents
{
    public static function read(string $path): string
    {
        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Unable to read "%s".', $path));
        }

        return $contents;
    }
}
