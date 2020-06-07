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

namespace Exchanger\Tests\Service;

use Exchanger\Contract\ExchangeRateService;
use Exchanger\Exception\ChainException;
use Exchanger\Exception\Exception;
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
        $pair = CurrencyPair::createFromString('EUR/USD');
        $query = new ExchangeRateQuery($pair);
        $rate = new ExchangeRate($pair, 1.0, new \DateTime(), PhpArray::class);

        $serviceOne = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceOne
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $serviceOne
            ->expects($this->once())
            ->method('getExchangeRate')
            ->with($query)
            ->will($this->throwException(new Exception()));

        $serviceTwo = $this->createMock('Exchanger\Contract\ExchangeRateService');

        $serviceTwo
            ->expects($this->once())
            ->method('supportQuery')
            ->will($this->returnValue(true));

        $serviceTwo
            ->expects($this->once())
            ->method('getExchangeRate')
            ->with($query)
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
        $fetchedRate = $chain->getExchangeRate($query);

        $this->assertSame($rate, $fetchedRate);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_all_providers_fail()
    {
        $exception = new Exception('Unsupported currency pair.');
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
            $this->assertEquals("The chain resulted in 2 exception(s):\r\nExchanger\Exception\Exception: Unsupported currency pair.\r\nExchanger\Exception\Exception: Unsupported currency pair.", $e->getMessage());
        }

        $this->assertTrue($caught);
    }

    /**
     * @test
     */
    public function it_has_a_name()
    {
        $service = new Chain();

        $this->assertSame('chain', $service->getName());
    }

    /**
     * @test
     */
    public function it_can_convert_multiple_times()
    {
        $generator = function (): \Generator {
            $serviceOne = $this->createMock(ExchangeRateService::class);
            $serviceOne
                ->method('supportQuery')
                ->willReturn(false);

            yield $serviceOne;

            $serviceTwo = $this->createMock(ExchangeRateService::class);
            $serviceTwo
                ->method('supportQuery')
                ->willReturn(true);

            $exchangeRate = new ExchangeRate(CurrencyPair::createFromString('EUR/USD'), 0.8, new \DateTimeImmutable(), 'mock');

            $serviceTwo->expects($this->exactly(2))
                ->method('getExchangeRate')
                ->willReturn($exchangeRate);

            yield $serviceTwo;
        };

        $chain = new Chain($generator());

        $query = new ExchangeRateQuery(CurrencyPair::createFromString('EUR/USD'));

        $this->assertTrue($chain->supportQuery($query));
        $exchangeRate = $chain->getExchangeRate($query);
        $this->assertEquals(0.8, $exchangeRate->getValue());

        $this->assertTrue($chain->supportQuery($query));
        $exchangeRate = $chain->getExchangeRate($query);
        $this->assertEquals(0.8, $exchangeRate->getValue());
    }
}
