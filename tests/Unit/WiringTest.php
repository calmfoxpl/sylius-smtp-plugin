<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusSmtpPlugin\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A Symfony plugin is assembled out of YAML, and a mistake there only shows on a live
 * installation: a service that names a class nobody wrote, a route pointing at a controller that
 * is not public, a template referenced by a name that does not exist. None of that is caught by
 * testing the core, and all of it is caught by reading the files against each other.
 *
 * The files are read as text rather than through Symfony's container, so this runs with no vendor
 * directory — which is what makes it useful while working on the plugin rather than only in CI.
 */
final class WiringTest extends TestCase
{
    private const NAMESPACE_PREFIX = 'Calmfox\\SyliusSmtpPlugin\\';

    private const TEMPLATE_PREFIX = '@CalmfoxSyliusSmtpPlugin/';

    public function testEveryClassNamedInTheContainerExists(): void
    {
        preg_match_all('/class: (Calmfox\\\\SyliusSmtpPlugin\\\\[A-Za-z0-9_\\\\]+)/', self::read('config/services.yaml'), $matches);
        self::assertGreaterThan(20, \count($matches[1]));

        foreach (array_unique($matches[1]) as $class) {
            self::assertFileExists(self::pathFor($class), sprintf('services.yaml names %s, which has no file', $class));
        }
    }

    /** A service referenced but never defined is a container that will not compile. */
    public function testEveryServiceReferencedIsDefined(): void
    {
        $services = self::read('config/services.yaml');
        preg_match_all('/^    (calmfox_smtp\.[a-z0-9_.]+):$/m', $services, $defined);
        preg_match_all('/@(calmfox_smtp\.[a-z0-9_.]+)/', $services, $referenced);

        self::assertNotEmpty($defined[1]);
        foreach (array_unique($referenced[1]) as $service) {
            self::assertContains($service, $defined[1], sprintf('%s is referenced but never defined', $service));
        }
    }

    /** Every route has to point at a controller service that exists and is public. */
    public function testEveryRoutePointsAtAPublicController(): void
    {
        $services = self::read('config/services.yaml');
        preg_match_all('/_controller: (calmfox_smtp\.[a-z0-9_.]+)/', self::read('config/routes/admin.yaml'), $matches);

        self::assertGreaterThan(5, \count($matches[1]));
        foreach (array_unique($matches[1]) as $controller) {
            $definition = self::definitionOf($services, $controller);
            self::assertNotNull($definition, sprintf('the route for %s has no service', $controller));
            self::assertStringContainsString('public: true', $definition, sprintf('%s is a controller but not public', $controller));
        }
    }

    /** Every route a template links to has to exist, or the page throws while rendering. */
    public function testEveryRouteNameUsedInATemplateIsDefined(): void
    {
        $routes = self::read('config/routes/admin.yaml');
        preg_match_all('/^(calmfox_smtp[a-z0-9_]*):$/m', $routes, $defined);
        self::assertNotEmpty($defined[1]);

        $used = [];
        foreach (self::filesIn('templates') as $template) {
            preg_match_all("/path\('(calmfox_smtp[a-z0-9_]*)'/", self::contents($template), $matches);
            foreach ($matches[1] as $route) {
                $used[$route] = basename($template);
            }
        }
        foreach (self::filesIn('src') as $file) {
            preg_match_all("/generate\('(calmfox_smtp[a-z0-9_]*)'/", self::contents($file), $matches);
            foreach ($matches[1] as $route) {
                $used[$route] = basename($file);
            }
        }
        foreach (self::filesIn('config') as $file) {
            preg_match_all("/route': '(calmfox_smtp[a-z0-9_]*)'/", self::contents($file), $matches);
            foreach ($matches[1] as $route) {
                $used[$route] = basename($file);
            }
        }

        self::assertNotEmpty($used);
        foreach ($used as $route => $where) {
            self::assertContains($route, $defined[1], sprintf('%s links to the route %s, which is not defined', $where, $route));
        }
    }

    /** Menu entries point at routes too, and a menu that throws takes the whole panel with it. */
    public function testTheMenuPointsAtItsOwnRoutes(): void
    {
        preg_match_all("/'route' => '(calmfox_smtp[a-z0-9_]*)'/", self::read('src/Menu/AdminMenuListener.php'), $matches);
        preg_match_all('/^(calmfox_smtp[a-z0-9_]*):$/m', self::read('config/routes/admin.yaml'), $defined);

        self::assertCount(3, $matches[1]);
        foreach ($matches[1] as $route) {
            self::assertContains($route, $defined[1]);
        }
    }

    /** Every template named anywhere is shipped with the plugin. */
    public function testEveryTemplateNamedAnywhereExists(): void
    {
        $named = [];
        foreach (array_merge(self::filesIn('src'), self::filesIn('templates'), self::filesIn('config')) as $file) {
            preg_match_all('#' . preg_quote(self::TEMPLATE_PREFIX, '#') . '([A-Za-z0-9_/.\-]+\.twig)#', self::contents($file), $matches);
            foreach ($matches[1] as $template) {
                $named[$template] = basename($file);
            }
        }

        self::assertGreaterThan(4, \count($named));
        foreach ($named as $template => $where) {
            self::assertFileExists(
                \dirname(__DIR__, 2) . '/templates/' . $template,
                sprintf('%s names %s, which is not in the plugin', $where, $template),
            );
        }
    }

    /**
     * The transport is the whole point of the plugin: without this it is a settings screen that
     * changes nothing. Decorating the transport rather than the mailer is deliberate — messages
     * sent through Messenger reach the transport and not the mailer.
     */
    public function testTheTransportDecoratesTheApplicationsOwn(): void
    {
        $services = self::read('config/services.yaml');

        self::assertStringContainsString('decorates: mailer.default_transport', $services);
        self::assertStringContainsString("\$inner: '@.inner'", $services);
    }

    /** Our entities are mapped by the plugin, so an application does not have to do it. */
    public function testTheEntitiesAreMappedByThePlugin(): void
    {
        $extension = self::read('src/DependencyInjection/CalmfoxSyliusSmtpExtension.php');

        self::assertStringContainsString("'type' => 'attribute'", $extension);
        self::assertStringContainsString('/Entity', $extension);
        self::assertStringContainsString("'prefix' => 'Calmfox\\SyliusSmtpPlugin\\Entity'", $extension);

        foreach (self::filesIn('src/Entity') as $entity) {
            self::assertStringContainsString('#[ORM\Entity]', self::contents($entity), basename($entity));
            self::assertStringContainsString('#[ORM\Table(name: \'calmfox_smtp_', self::contents($entity), basename($entity));
        }
    }

    /** Every setting the configuration tree accepts is one the provider actually reads. */
    public function testTheConfigurationTreeAndTheProviderAgree(): void
    {
        preg_match_all("/->(?:boolean|scalar|integer|enum)Node\('([a-z_]+)'\)/", self::read('src/DependencyInjection/Configuration.php'), $tree);
        $provider = self::read('src/Settings/SettingsProvider.php');

        self::assertNotEmpty($tree[1]);
        foreach (array_unique($tree[1]) as $node) {
            self::assertStringContainsString(
                "'" . $node . "'",
                $provider,
                sprintf('the configuration accepts %s, which nothing reads', $node),
            );
        }
    }

    private static function definitionOf(string $services, string $service): ?string
    {
        $start = strpos($services, "\n    " . $service . ":\n");
        if (false === $start) {
            return null;
        }
        $next = strpos($services, "\n    calmfox_smtp.", $start + 1);

        return substr($services, $start, false === $next ? null : $next - $start);
    }

    private static function pathFor(string $class): string
    {
        return \dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', str_replace(self::NAMESPACE_PREFIX, '', $class)) . '.php';
    }

    private static function read(string $relative): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relative;
        self::assertFileExists($path);

        return self::contents($path);
    }

    private static function contents(string $path): string
    {
        return (string) file_get_contents($path);
    }

    /** @return list<string> */
    private static function filesIn(string $relative): array
    {
        $directory = \dirname(__DIR__, 2) . '/' . $relative;
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
