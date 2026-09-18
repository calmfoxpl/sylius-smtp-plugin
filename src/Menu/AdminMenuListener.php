<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Menu;

use Calmfox\SyliusSmtpPlugin\Health\HealthStore;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

/**
 * Three entries under Configuration, and a mark on the first one when sending is broken.
 *
 * The mark is the point. Somebody who logs in to process yesterday's orders is not looking for a
 * mail settings page, and the menu is the one thing they do look at.
 */
final class AdminMenuListener
{
    public function __construct(
        private readonly HealthStore $store,
        private readonly SettingsProvider $settings,
    ) {
    }

    public function __invoke(MenuBuilderEvent $event): void
    {
        $configuration = $event->getMenu()->getChild('configuration');
        if (null === $configuration) {
            return;
        }

        $broken = $this->settings->warnsInPanel() && $this->store->load()->isBroken();

        $configuration
            ->addChild('calmfox_smtp_health', ['route' => 'calmfox_smtp_health'])
            ->setLabel($broken ? 'calmfox_smtp.menu.health_broken' : 'calmfox_smtp.menu.health')
            ->setLabelAttribute('icon', $broken ? 'tabler:mail-x' : 'tabler:mail-check')
        ;

        $configuration
            ->addChild('calmfox_smtp_settings', ['route' => 'calmfox_smtp_settings'])
            ->setLabel('calmfox_smtp.menu.settings')
            ->setLabelAttribute('icon', 'tabler:mail-cog')
        ;

        $configuration
            ->addChild('calmfox_smtp_log', ['route' => 'calmfox_smtp_log'])
            ->setLabel('calmfox_smtp.menu.log')
            ->setLabelAttribute('icon', 'tabler:mail-opened')
        ;
    }
}
