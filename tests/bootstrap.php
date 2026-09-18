<?php

/**
 * Composer's autoloader when there is one, and a PSR-4 fallback when there is not.
 *
 * The unit tests cover src/Core, which knows nothing about Symfony or Sylius, so they run in a
 * checkout with no `composer install` and no Sylius at all — which is what makes them usable as
 * a quick check while working on the plugin. Functional tests need the test application, and
 * therefore need the vendor directory.
 */

declare(strict_types=1);

$autoload = \dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;

    return;
}

spl_autoload_register(static function (string $class): void {
    $roots = [
        'Tests\\Calmfox\\SyliusSmtpPlugin\\' => __DIR__ . '/',
        'Calmfox\\SyliusSmtpPlugin\\' => \dirname(__DIR__) . '/src/',
    ];

    foreach ($roots as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $path = $directory . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }

        return;
    }
});
