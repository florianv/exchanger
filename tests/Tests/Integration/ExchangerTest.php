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

namespace Exchanger\Tests\Integration;

use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\HistoricalExchangeRateQuery;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Exchanger\Exchanger;
use Exchanger\Service\Fixer;
use PHPUnit\Framework\TestCase;

class ExchangerTest extends TestCase
{
    private $fixerAccessKey;

    protected function setUp(): void
    {
        $this->fixerAccessKey = getenv('FIXER_ACCESS_KEY');
    }

    public function testCacheWithExchangeQuery()
    {
        $this->cacheTest(function () {
            return $this->createExchangeRateQuery();
        });
    }

    public function testCacheWithHistoricalQuery()
    {
        $this->cacheTest(function () {
            return $this->createHistoricalExchangeRateQuery();
        });
    }

    private function cacheTest(callable $provideQuery)
    {
        if (!$this->fixerAccessKey) {
            $this->markTestSkipped('FIXER.IO ACCESS KEY IS NOT SET. SKIPPING THE CACHE INTEGRATION TEST');
        }

        $firstStart = microtime(true);
        $firstRate = $this->createCachedExchanger()->getExchangeRate(call_user_func($provideQuery));
        $firstEnd = microtime(true) - $firstStart;

        $secondStart = microtime(true);
        $secondRate = $this->createCachedExchanger()->getExchangeRate(call_user_func($provideQuery));
        $secondEnd = microtime(true) - $secondStart;

        $thirdStart = microtime(true);
        $thirdRate = $this->createCachedExchanger()->getExchangeRate(call_user_func($provideQuery));
        $thirdEnd = microtime(true) - $thirdStart;

        $this->assertEquals($firstRate, $secondRate, $thirdRate);
        $this->assertLessThan(100 * $firstEnd, $secondEnd);
        $this->assertLessThan(100 * $firstEnd, $thirdEnd);
    }

    /**
     * Creates the file cache item pool.
     *
     * @return FilesystemCachePool
     */
    private function createFileCacheItemPool()
    {
        $filesystemAdapter = new Local(sys_get_temp_dir().'/exchanger-tests');
        $filesystem = new Filesystem($filesystemAdapter);

        restore_error_handler();

        return new FilesystemCachePool($filesystem);
    }

    /**
     * Creates a cached exchanger.
     *
     * @param int $ttl
     *
     * @return Exchanger
     */
    private function createCachedExchanger($ttl = 3600)
    {
        return new Exchanger(
            new Fixer(null, null, ['access_key' => $this->fixerAccessKey]),
            $this->createFileCacheItemPool(),
            ['cache_ttl' => $ttl]
        );
    }

    /**
     * Creates an historical exchange rate query.
     *
     * @param string $date
     *
     * @return HistoricalExchangeRateQuery
     */
    private function createHistoricalExchangeRateQuery($date = 'yesterday')
    {
        return new HistoricalExchangeRateQuery(
            CurrencyPair::createFromString('EUR/USD'),
            new \DateTime($date),
            ['cache_ttl' => 60]
        );
    }

    /**
     * Creates an exchange rate query.
     *
     * @return ExchangeRateQuery
     */
    private function createExchangeRateQuery()
    {
        return new ExchangeRateQuery(CurrencyPair::createFromString('EUR/GBP'));
    }
}
