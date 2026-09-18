<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Twig;

use Calmfox\SyliusSmtpPlugin\Health\HealthStore;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `calmfox_smtp_warning()` for the templates that show the notice.
 *
 * Returns nothing at all unless sending is broken, so a working shop shows no trace of this
 * plugin on its dashboard: a panel that reports good news in six places is a panel nobody reads.
 * It only ever reads the stored verdict — a dashboard must not wait on a mail server.
 */
final class HealthExtension extends AbstractExtension
{
    public function __construct(
        private readonly HealthStore $store,
        private readonly SettingsProvider $settings,
        private readonly Wording $wording,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('calmfox_smtp_warning', $this->warning(...))];
    }

    /** @return array{headline: string, lines: list<string>}|null */
    public function warning(): ?array
    {
        if (!$this->settings->warnsInPanel()) {
            return null;
        }

        $state = $this->store->load();
        if (!$state->isBroken()) {
            return null;
        }

        $settings = $this->settings->resolve()->settings;

        return [
            'headline' => $this->wording->headline($state, $settings),
            'lines' => $this->wording->explain($state, $settings),
        ];
    }
}
