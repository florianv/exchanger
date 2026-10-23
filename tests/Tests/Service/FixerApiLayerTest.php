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

use Exchanger\CurrencyPair;
use Exchanger\Exception\Exception;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use Exchanger\Service\FixerApiLayer;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;

class FixerApiLayerTest extends ServiceTestCase
{
    #[Test]
    public function it_throws_an_exception_if_api_key_option_missing()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "api_key" option must be provided to use fixer.io');

        new FixerApiLayer($this->createStub('Http\Client\HttpClient'));
    }

    #[Test]
    public function it_supports_all_queries()
    {
        $service = new FixerApiLayer($this->createStub('Http\Client\HttpClient'), null, ['api_key' => 'x']);

        $this->assertTrue($service->supportQuery(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR'))));
    }

    #[Test]
    public function it_throws_an_exception_with_error_response()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The current subscription plan does not support this API endpoint.');

        $uri = 'https://api.apilayer.com/fixer/latest?base=USD';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/FixerApiLayer/error.json');

        $service = new FixerApiLayer($this->getHttpAdapterMock($uri, $content), null, ['api_key' => 'x']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('USD/EUR')));
    }

    #[Test]
    public function it_throws_an_exception_when_the_pair_is_not_supported()
    {
        $this->expectException(UnsupportedCurrencyPairException::class);

        $uri = 'https://api.apilayer.com/fixer/latest?base=EUR';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/FixerApiLayer/latest.json');

        $service = new FixerApiLayer($this->getHttpAdapterMock($uri, $content), null, ['api_key' => 'x']);
        $service->getExchangeRate(new ExchangeRateQuery(CurrencyPair::createFromString('EUR/XXX')));
    }

    #[Test]
    public function it_fetches_a_rate()
    {
        $pair = CurrencyPair::createFromString('EUR/CHF');
        $uri = 'https://api.apilayer.com/fixer/latest?base=EUR';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/FixerApiLayer/latest.json');
        $sendsApiKeyHeader = static fn(RequestInterface $request): bool => 'x' === $request->getHeaderLine('apikey');

        $service = new FixerApiLayer($this->getHttpAdapterMock($uri, $content, 200, $sendsApiKeyHeader), null, ['api_key' => 'x']);
        $rate = $service->getExchangeRate(new ExchangeRateQuery($pair));

        $this->assertEquals(1.0933, $rate->getValue());
        $this->assertEquals(new \DateTime('2016-08-26'), $rate->getDate());
        $this->assertEquals('fixer_apilayer', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_fetches_a_historical_rate()
    {
        $pair = CurrencyPair::createFromString('USD/AUD');
        $uri = 'https://api.apilayer.com/fixer/2000-01-03?base=USD';
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Service/FixerApiLayer/historical.json');
        $date = new \DateTime('2000-01-03');

        $service = new FixerApiLayer($this->getHttpAdapterMock($uri, $content), null, ['api_key' => 'x']);
        $rate = $service->getExchangeRate(new HistoricalExchangeRateQuery($pair, $date));

        $this->assertEquals(1.5209, $rate->getValue());
        $this->assertEquals($date, $rate->getDate());
        $this->assertEquals('fixer_apilayer', $rate->getProviderName());
        $this->assertSame($pair, $rate->getCurrencyPair());
    }

    #[Test]
    public function it_has_a_name()
    {
        $service = new FixerApiLayer($this->createStub('Http\Client\HttpClient'), null, ['api_key' => 'x']);

        $this->assertSame('fixer_apilayer', $service->getName());
    }
}
