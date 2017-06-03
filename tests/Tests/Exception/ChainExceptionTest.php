<?php

namespace Exchanger\Tests\Exception;

use Exchanger\Exception\ChainException;
use Exchanger\Exception\InternalException;

/**
 * @covers \Exchanger\Exception\ChainException
 */
class ChainExceptionTest extends \PHPUnit_Framework_TestCase
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

    public function setUp()
    {
        $this->exception1 = new InternalException('Something bad happened.');
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
        $this->assertSame("The chain resulted in 2 exception(s):\r\nExchanger\\Exception\\InternalException: Something bad happened.\r\nException: General exception.", $this->chainException->getMessage());
        $this->assertCount(2, $this->chainException->getExceptions());
    }
}
