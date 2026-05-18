<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS3x0' => true
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__)
    )
;
