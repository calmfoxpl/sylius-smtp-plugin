<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Twig;

use Calmfox\SyliusSmtpPlugin\Dns\DomainCheck;
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
        private readonly DomainCheck $domains,
        private readonly SettingsProvider $settings,
        private readonly Wording $wording,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('calmfox_smtp_warning', $this->warning(...)),
            new TwigFunction('calmfox_smtp_deliverability', $this->deliverability(...)),
        ];
    }

    /**
     * The other notice, in the other colour: the shop is sending and the mail may not be
     * arriving. Separate from the one above on purpose — sharing a colour with "cannot send"
     * would teach an administrator to ignore both within a week.
     *
     * Reads only what the scheduled command stored; a dashboard must never wait on a nameserver.
     *
     * @return array{headline: string, lines: list<string>}|null
     */
    public function deliverability(): ?array
    {
        if (!$this->settings->warnsInPanel()) {
            return null;
        }

        $verdict = $this->domains->stored();
        if (null === $verdict || !$verdict->isDeliverabilityAtRisk()) {
            return null;
        }

        $lines = [];
        foreach ($verdict->issues as $issue) {
            $lines[] = $this->wording->issue($issue);
        }

        return [
            'headline' => $this->wording->deliverabilityHeadline($verdict->domain),
            'lines' => $lines,
        ];
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
