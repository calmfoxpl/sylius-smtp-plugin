<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\DependencyInjection;

use Calmfox\SyliusSmtpPlugin\Core\Log\Retention;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * What can be set in `config/packages/`, as opposed to in the panel.
 *
 * The split matters. The server and the credentials belong in the panel, where the person with the
 * provider's dashboard open can paste them — and in the database rather than in a deployment,
 * because a rotated API key should not need one. Everything about *how the module behaves* can
 * also be pinned in YAML, because on a shop where deployments are managed those decisions belong
 * with the deployment: how patient the health check is, where warnings go, how long the log is
 * kept.
 *
 * Anything set here is the default the panel starts from; `locked` makes it the last word, and
 * the panel then shows the settings read-only instead of offering fields that quietly do nothing.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('calmfox_sylius_smtp');
        $root = $tree->getRootNode();

        $root
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->booleanNode('locked')
                    ->defaultFalse()
                    ->info('Settings come from this file only; the panel shows them read-only.')
                ->end()
                ->arrayNode('server')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('provider')->defaultValue('custom')->end()
                        ->scalarNode('host')->defaultNull()->end()
                        ->integerNode('port')->min(1)->max(65535)->defaultNull()->end()
                        ->enumNode('encryption')->values(['tls', 'ssl', ''])->defaultValue('tls')->end()
                        ->enumNode('auth')->values(['auto', 'login', 'plain', 'crammd5', 'none'])->defaultValue('auto')->end()
                        ->scalarNode('username')->defaultNull()->end()
                        ->scalarNode('password')->defaultNull()->info('Use an environment variable; never a literal.')->end()
                        ->integerNode('timeout')
                            ->min(MailSettings::MIN_TIMEOUT)
                            ->max(MailSettings::MAX_TIMEOUT)
                            ->defaultValue(MailSettings::DEFAULT_TIMEOUT)
                        ->end()
                        ->booleanNode('verify_certificate')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('sender')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('from_email')->defaultNull()->end()
                        ->scalarNode('from_name')->defaultNull()->end()
                        ->scalarNode('return_path')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('health')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->integerNode('interval')->min(60)->defaultValue(900)->end()
                        ->integerNode('failed_checks')->min(1)->defaultValue(2)->end()
                        ->integerNode('send_failures')->min(1)->defaultValue(3)->end()
                        ->integerNode('send_window')->min(60)->defaultValue(1800)->end()
                        ->integerNode('repeat_after')->min(300)->defaultValue(21600)->end()
                    ->end()
                ->end()
                ->arrayNode('dns')
                    ->addDefaultsIfNotSet()
                    ->info('Reading what the sending domain publishes: SPF, DKIM and DMARC.')
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('alerts')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('panel')->defaultTrue()->end()
                        ->booleanNode('email')->defaultTrue()->end()
                        ->scalarNode('recipient')->defaultNull()->end()
                        ->booleanNode('webhook')->defaultFalse()->end()
                        ->scalarNode('webhook_url')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('log')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->booleanNode('store_body')->defaultFalse()->end()
                        ->integerNode('retention_days')->min(1)->max(Retention::MAX_DAYS)->defaultValue(Retention::DEFAULT_DAYS)->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
