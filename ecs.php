<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $config): void {
    $config->import('vendor/sylius-labs/coding-standard/ecs.php');
    // Only the directories this plugin actually has: there is no functional suite and no test
    // application of its own, because the unit suite covers the framework-free core and the
    // wiring, and it runs without Sylius installed.
    $config->paths(['src', 'tests/Unit', 'tests/Support', 'tests/bootstrap.php', 'ecs.php']);
};
