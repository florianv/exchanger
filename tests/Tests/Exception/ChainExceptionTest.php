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

namespace Exchanger\Tests\Exception;

use Exchanger\Exception\ChainException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Exchanger\Exception\ChainException
 */
class ChainExceptionTest extends TestCase
{
    /**
     * @var ChainException
     */
    private $chainException;

    /**
     * @var \Exception
     */
    private $exception1;

    /**
     * @var \Exception
     */
    private $exception2;

    protected function setUp(): void
    {
        $this->exception1 = new \Exception('Something bad happened.');
        $this->exception2 = new \Exception('General exception.');

        $this->chainException = new ChainException([
            $this->exception1,
            $this->exception2,
        ]);
    }

    /**
     * @test
     */
    public function it_should_have_a_descriptive_error_message()
    {
        $this->assertSame("The chain resulted in 2 exception(s):\r\nException: Something bad happened.\r\nException: General exception.", $this->chainException->getMessage());
        $this->assertCount(2, $this->chainException->getExceptions());
    }
}
