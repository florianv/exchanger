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

use Exchanger\Service\Registry;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    /**
     * @test
     */
    public function it_provides_all_services()
    {
        $excludedServices = [
            'Exchanger\Service\Service',
            'Exchanger\Service\HttpService',
            'Exchanger\Service\Chain',
        ];

        $classes = get_declared_classes();
        $services = [];

        foreach ($classes as $class) {
            $reflect = new \ReflectionClass($class);

            if (!in_array($class, $excludedServices) &&
                false === strpos($class, 'Mock') &&
                $reflect->implementsInterface('Exchanger\Contract\ExchangeRateService')
            ) {
                $services[] = $class;
            }
        }

        $registryServices = Registry::getServices();
        $missingServices = array_diff($services, $registryServices);

        $this->assertEquals([], $missingServices);
    }
}
