<?php

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

class ExchangerTest extends \PHPUnit_Framework_TestCase
{
    private $fixerAccessKey;

    public function setUp()
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
            fwrite(STDERR, "\nFIXER.IO ACCESS KEY IS NOT SET. SKIPPING THE CACHE INTEGRATION TEST.\n");

            return;
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
        $this->assertTrue($secondEnd < (100 * $firstEnd));
        $this->assertTrue($thirdEnd < (100 * $firstEnd));
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
     * @return HistoricalExchangeRateQuery
     */
    private function createExchangeRateQuery()
    {
        return new ExchangeRateQuery(CurrencyPair::createFromString('EUR/GBP'));
    }
}
