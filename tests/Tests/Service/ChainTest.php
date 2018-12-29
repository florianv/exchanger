<?php

/*
 * This file is part of Exchanger.
 *
 * (c) Florian Voutzinos <florian@voutzinos.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Tests\Service;

use Exchanger\Exception\ChainException;
use Exchanger\Exception\Exception;
use Exchanger\Exception\InternalException;
use Exchanger\ExchangeRate;
use Exchanger\ExchangeRateQuery;
use Exchanger\CurrencyPair;
use Exchanger\Service\Chain;
use Exchanger\Service\PhpArray;
use PHPUnit\Framework\TestCase;

class ChainTest extends TestCase
{
    /**
     * @test
     */
    public function it_does_not_support_all_queries()
    {
        // Supported
        $serviceOne = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceOne
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $serviceTwo = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceTwo
            ->expects($this->never())
            ->method('supportQuery')
            ->will($this->returnValue(false));

        $chain = new Chain([$serviceOne, $serviceTwo]);

        $this->assertTrue($chain->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('TRY/EUR'))));

        // Not Supported
        $serviceOne = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceOne
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(false));

        $serviceTwo = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceTwo
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(false));

        $chain = new Chain([$serviceOne, $serviceTwo]);

        $this->assertFalse($chain->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('TRY/EUR'))));
    }

    /**
     * @test
     */
    public function it_use_next_provider_in_the_chain()
    {
        $pair = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));
        $rate = new ExchangeRate(1, PhpArray::class, new \DateTime());

        $serviceOne = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceOne
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $serviceOne
            ->expects($this->once())
            ->method('getExchangeRate')
            ->with($pair)
            ->will($this->throwException(new Exception()));

        $serviceTwo = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceTwo
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $serviceTwo
            ->expects($this->once())
            ->method('getExchangeRate')
            ->with($pair)
            ->will($this->returnValue($rate));

        $serviceThree = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceThree
            ->expects($this->never())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $serviceThree
            ->expects($this->never())
            ->method('getExchangeRate');

        $chain = new Chain([$serviceOne, $serviceTwo, $serviceThree]);
        $fetchedRate = $chain->getExchangeRate($pair);

        $this->assertSame($rate, $fetchedRate);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_all_providers_fail()
    {
        $exception = new Exception();
        $serviceOne = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceOne
            ->expects($this->once())
            ->method('getExchangeRate')
            ->will($this->throwException($exception));

        $serviceOne
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $serviceTwo = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceTwo
            ->expects($this->once())
            ->method('getExchangeRate')
            ->will($this->throwException($exception));

        $serviceTwo
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $chain = new Chain([$serviceOne, $serviceTwo]);
        $caught = false;

        try {
            $chain->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD')));
        } catch (ChainException $e) {
            $caught = true;
            $this->assertEquals([$exception, $exception], $e->getExceptions());
        }

        $this->assertTrue($caught);
    }

    /**
     * @test
     * @expectedException \Exchanger\Exception\InternalException
     */
    public function it_throws_an_exception_when_an_internal_exception_is_thrown()
    {
        $internalException = new InternalException();

        $serviceOne = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceOne
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $serviceOne
            ->expects($this->once())
            ->method('getExchangeRate')
            ->will($this->throwException($internalException));

        $serviceTwo = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceTwo
            ->expects($this->never())
            ->method('getExchangeRate');

        $chain = new Chain([$serviceOne, $serviceTwo]);
        $chain->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD')));
    }
}
