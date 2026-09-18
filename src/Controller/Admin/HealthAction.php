<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Controller\Admin;

use Calmfox\SyliusSmtpPlugin\Core\Health\Status;
use Calmfox\SyliusSmtpPlugin\Core\Provider\ProviderCatalog;
use Calmfox\SyliusSmtpPlugin\Dns\DomainCheck;
use Calmfox\SyliusSmtpPlugin\Health\Monitor;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The report page.
 *
 * Written to be read by somebody who has just been told the shop cannot send e-mail and does not
 * yet know what SMTP stands for — and to be forwarded to somebody who does. So it says the state
 * in a sentence, then the one thing to check, then the server's own words, and it repeats every
 * time that a working connection is not a proven delivery. That sentence is the difference
 * between this page and the kind of green tick that costs a shop a day of orders.
 *
 * Nothing here dials a mail server: the page shows the stored verdict, and the button asks for a
 * new one.
 */
final readonly class HealthAction
{
    public function __construct(
        private Monitor $monitor,
        private DomainCheck $domains,
        private SettingsProvider $settings,
        private Wording $wording,
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        $resolved = $this->settings->resolve();
        $state = $this->monitor->state();
        $provider = ProviderCatalog::get($resolved->settings->providerId);

        return new Response($this->twig->render('@CalmfoxSyliusSmtpPlugin/admin/health.html.twig', [
            'enabled' => $resolved->settings->enabled,
            'state' => $state,
            'settings' => $resolved->settings,
            'provider' => $provider,
            'status_label' => $this->wording->status($state->status),
            'modifier' => match ($state->status) {
                Status::OK => 'success',
                Status::WARN => 'warning',
                Status::FAIL => 'danger',
                default => 'secondary',
            },
            'headline' => $this->wording->headline($state, $resolved->settings),
            'explanation' => Status::UNKNOWN === $state->status
                ? [$this->wording->say('report.never_checked')]
                : $this->wording->explain($state, $resolved->settings),
            'issues' => array_map(
                fn ($issue): array => ['severity' => $issue->severity, 'text' => $this->wording->issue($issue)],
                $resolved->issues,
            ),
            'channels' => $this->channels(),
            'test_recipient' => $this->settings->alertRecipient(),
            'domain' => $this->domain(),
        ]));
    }

    /**
     * What the sender domain publishes, as the page needs it.
     *
     * Only ever the stored answer: the resolver has no timeout worth the name, and a report page
     * that hung for ten seconds to say an SPF record is wrong would be a worse fault than the
     * one it is reporting.
     *
     * @return array<string, mixed>
     */
    private function domain(): array
    {
        if (!$this->domains->isEnabled()) {
            return ['enabled' => false];
        }

        $verdict = $this->domains->stored();
        if (null === $verdict) {
            return ['enabled' => true, 'checked' => false, 'headline' => $this->wording->say('deliverability.never_checked')];
        }

        $facts = [];
        if (null !== $verdict->domain) {
            $facts[$this->wording->say('report.domain.sends_as')] = $verdict->domain;
            $facts[$this->wording->say('report.domain.spf')] = $this->wording->domainFact('spf', $verdict->asked ? $verdict->spfFound : null);

            if ($verdict->spfFound) {
                $facts[$this->wording->say('report.domain.spf_provider')] = $this->wording->domainFact('spf_provider', $verdict->spfAuthorizesProvider);
                $facts[$this->wording->say('report.domain.spf_cost')] = $this->wording->say('report.domain.lookups', ['%count%' => (string) $verdict->spfLookupCost]);
            }

            $facts[$this->wording->say('report.domain.dkim')] = $this->wording->domainFact('dkim', $verdict->dkimFound);
            $facts[$this->wording->say('report.domain.dmarc')] = $verdict->dmarcPolicy ?? $this->wording->domainFact('dmarc', false);

            if (null !== $verdict->otherSender) {
                $facts[$this->wording->say('report.domain.also_authorised')] = $verdict->otherSender;
            }
        }

        return [
            'enabled' => true,
            'checked' => true,
            'at_risk' => $verdict->isDeliverabilityAtRisk(),
            'headline' => $verdict->isDeliverabilityAtRisk()
                ? $this->wording->deliverabilityHeadline($verdict->domain)
                : $this->wording->deliverabilityFine($verdict->domain),
            'facts' => $facts,
            'issues' => array_map(
                fn ($issue): array => ['severity' => $issue->severity, 'text' => $this->wording->issue($issue)],
                $verdict->issues,
            ),
        ];
    }

    /**
     * Where a warning would go if one were raised now.
     *
     * Shown because the commonest failure of a warning system is that nobody set it up: a shop
     * with every channel switched off has a health check that talks to itself.
     *
     * @return list<string>
     */
    private function channels(): array
    {
        $channels = [];
        if ($this->settings->warnsInPanel()) {
            $channels[] = $this->wording->say('report.channel.panel');
        }
        if ($this->settings->warnsByEmail()) {
            $channels[] = $this->wording->say('report.channel.email', ['%recipient%' => $this->settings->alertRecipient()]);
        }
        if ($this->settings->warnsByWebhook()) {
            $channels[] = $this->wording->say('report.channel.webhook');
        }
        $channels[] = $this->wording->say('report.channel.log');

        return $channels;
    }
}
