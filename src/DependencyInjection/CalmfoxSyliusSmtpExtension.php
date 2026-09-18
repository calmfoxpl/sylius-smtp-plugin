<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class CalmfoxSyliusSmtpExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array<string, mixed> $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        // The whole processed tree, handed to the settings provider as one value: it is the file
        // half of "the file, then the panel", and keeping it in one piece is what lets that
        // precedence live in one readable method.
        $container->setParameter('calmfox_sylius_smtp.configuration', $config);
        $container->setParameter('calmfox_sylius_smtp.locked', $config['locked']);

        (new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config')))->load('services.yaml');
    }

    /** The plugin's own entities are mapped by the plugin, so the application does not have to. */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'CalmfoxSyliusSmtpPlugin' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => \dirname(__DIR__) . '/Entity',
                        'prefix' => 'Calmfox\SyliusSmtpPlugin\Entity',
                        'alias' => 'CalmfoxSyliusSmtpPlugin',
                    ],
                ],
            ],
        ]);
    }
}
