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
use Exchanger\Service\Chain;
use Exchanger\Service\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    /**
     * Services that implement ExchangeRateService but are not providers.
     */
    private const NOT_REGISTERED = [
        Chain::class,
    ];

    #[Test]
    public function it_provides_all_services()
    {
        $missingServices = array_values(array_diff(self::findServiceClasses(), Registry::getServices(), self::NOT_REGISTERED));

        $this->assertSame([], $missingServices, 'Add these services to Registry::getServices().');
    }

    #[Test]
    public function it_uses_unique_identifiers_for_services()
    {
        $services = Registry::getServices();

        $this->assertSame(array_values($services), array_values(array_unique($services)));
    }

    #[Test]
    #[DataProvider('provideRegisteredServices')]
    public function it_uses_a_lowercase_snake_case_identifier(string $identifier, string $class)
    {
        $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $identifier);
    }

    #[Test]
    #[DataProvider('provideRegisteredServices')]
    public function it_registers_each_service_under_its_name(string $identifier, string $class)
    {
        /** @var ExchangeRateService $service */
        $service = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

        $this->assertSame($identifier, $service->getName(), \sprintf('%s::getName() must return its registry identifier.', $class));
    }

    public static function provideRegisteredServices(): iterable
    {
        foreach (Registry::getServices() as $identifier => $class) {
            yield $identifier => [$identifier, $class];
        }
    }

    /**
     * Scans src/Service instead of relying on get_declared_classes(), so a service is found even when no test loads it.
     *
     * @return list<class-string<ExchangeRateService>>
     */
    private static function findServiceClasses(): array
    {
        $directory = \dirname(__DIR__, 3) . '/src/Service';
        $classes = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ('php' !== $file->getExtension() || str_contains($file->getPathname(), '/resources/')) {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen($directory) + 1, -4);
            $class = 'Exchanger\\Service\\' . str_replace('/', '\\', $relativePath);

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isInstantiable() && $reflection->implementsInterface(ExchangeRateService::class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }
}
