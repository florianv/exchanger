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

use Exchanger\Service\FixerApiLayer;
use Exchanger\Service\PhpArray;
use Exchanger\Service\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Checks the conventions reviewers otherwise point out by hand on every new provider.
 */
class RepositoryConventionsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /**
     * Registered services that are deliberately absent from the README provider table, with the reason.
     */
    private const NOT_IN_README = [
        PhpArray::class => 'in-memory service, not a provider',
        FixerApiLayer::class => 'APILayer Fixer variant sending the key as a header; the README lists apilayer_fixer',
    ];

    #[Test]
    #[DataProvider('provideRegisteredServices')]
    public function it_has_a_test_for_each_service(string $identifier, string $class)
    {
        $relativePath = str_replace('\\', '/', substr($class, \strlen('Exchanger\\Service\\')));

        $this->assertFileExists(self::ROOT . '/tests/Tests/Service/' . $relativePath . 'Test.php');
    }

    #[Test]
    #[DataProvider('provideRegisteredServices')]
    public function it_lists_each_service_in_the_readme(string $identifier, string $class)
    {
        if (isset(self::NOT_IN_README[$class])) {
            $this->markTestSkipped(self::NOT_IN_README[$class]);
        }

        $this->assertStringContainsString(
            '`' . $identifier . '`',
            file_get_contents(self::ROOT . '/README.md'),
            \sprintf('Add a row for `%s` to the provider table in README.md.', $identifier),
        );
    }

    #[Test]
    #[DataProvider('provideRegisteredServices')]
    public function it_documents_each_service(string $identifier, string $class)
    {
        $this->assertStringContainsString(
            '`' . $class . '`',
            file_get_contents(self::ROOT . '/doc/readme.md'),
            \sprintf('Add `%s` to the "Commercial services" or "Public services" table in doc/readme.md.', $class),
        );
    }

    #[Test]
    #[DataProvider('providePhpFiles')]
    public function it_has_the_licence_header(string $file)
    {
        $this->assertStringContainsString(
            "/*\n * This file is part of Exchanger.\n",
            file_get_contents($file),
            'Copy the licence header from any other PHP file.',
        );
    }

    #[Test]
    #[DataProvider('provideServiceFiles')]
    public function it_depends_on_query_contracts_in_services(string $file)
    {
        $this->assertDoesNotMatchRegularExpression(
            '/\bExchanger\\\\(?:Historical)?ExchangeRateQuery\b/',
            file_get_contents($file),
            'Use Exchanger\Contract\ExchangeRateQuery and Exchanger\Contract\HistoricalExchangeRateQuery; '
            . 'an instanceof check on the concrete class misses other implementations of the contract.',
        );
    }

    public static function provideRegisteredServices(): iterable
    {
        foreach (Registry::getServices() as $identifier => $class) {
            yield $identifier => [$identifier, $class];
        }
    }

    public static function providePhpFiles(): iterable
    {
        foreach (['src', 'tests'] as $directory) {
            yield from self::findPhpFiles($directory);
        }
    }

    public static function provideServiceFiles(): iterable
    {
        yield from self::findPhpFiles('src/Service');
    }

    /**
     * @return iterable<string, array{string}>
     */
    private static function findPhpFiles(string $directory): iterable
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/' . $directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            // resources/ holds generated data files, not code.
            if ('php' === $file->getExtension() && !str_contains($file->getPathname(), '/resources/')) {
                $path = $file->getPathname();

                yield substr($path, \strlen(self::ROOT) + 1) => [$path];
            }
        }
    }
}
