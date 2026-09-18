<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Controller\Admin;

use Calmfox\SyliusSmtpPlugin\Core\Dns\Suggestion;
use Calmfox\SyliusSmtpPlugin\Core\Provider\ProviderCatalog;
use Calmfox\SyliusSmtpPlugin\Dns\DomainCheck;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Asks the sender domain what it publishes, now, because somebody pressed a button.
 *
 * The one place in the plugin where a wait is honest: there is a person in front of the screen
 * who asked the question. It answers in the same shape as the connection check and the test
 * message, so one browser module renders all three.
 *
 * It does two things at once, which is what the button is for: says whether the domain
 * authorises the provider that is configured, and — where the settings are still empty or point
 * elsewhere — says what the domain looks like it is already set up for.
 */
final readonly class DnsAction
{
    public function __construct(
        private DomainCheck $domains,
        private SettingsProvider $settings,
        private Wording $wording,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        if (!$this->domains->isEnabled()) {
            return new JsonResponse([
                'ok' => false,
                'headline' => $this->wording->say('dns.switched_off'),
                'lines' => [],
            ]);
        }

        $verdict = $this->domains->refresh();
        $lines = [];

        foreach ($verdict?->issues ?? [] as $issue) {
            $lines[] = $this->wording->issue($issue);
        }
        foreach ($this->suggestionLines() as $line) {
            $lines[] = $line;
        }

        $atRisk = true === $verdict?->isDeliverabilityAtRisk();

        return new JsonResponse([
            'ok' => !$atRisk,
            'headline' => $atRisk
                ? $this->wording->deliverabilityHeadline($verdict?->domain)
                : $this->wording->deliverabilityFine($verdict?->domain),
            'lines' => array_values(array_unique($lines)),
        ]);
    }

    /**
     * What the domain says it is already set up for, offered as a question.
     *
     * Only worth saying while the settings are empty or point elsewhere: telling somebody who
     * has just configured Brevo that their domain authorises Brevo is noise.
     *
     * @return list<string>
     */
    private function suggestionLines(): array
    {
        $settings = $this->settings->resolve()->settings;
        $suggestion = $this->domains->suggest();
        if (!$suggestion->hasAnything()) {
            return [];
        }

        $lines = [];

        if (null !== $suggestion->providerId && $suggestion->providerId !== $settings->providerId) {
            $provider = ProviderCatalog::get($suggestion->providerId);
            $lines[] = $this->wording->say('suggestion.provider_elsewhere', [
                '%provider%' => $provider->isCustom() ? $this->wording->say('form.provider_custom') : $provider->label,
            ]);
        }
        if (null !== $suggestion->otherSender) {
            $lines[] = $this->wording->say('suggestion.other_sender', ['%name%' => $suggestion->otherSender]);
        }
        if (Suggestion::FROM_SRV === $suggestion->source && null !== $suggestion->host && '' === $settings->host) {
            $lines[] = $this->wording->say('suggestion.srv', [
                '%host%' => $suggestion->host,
                '%port%' => (string) $suggestion->port,
            ]);
        }

        return $lines;
    }
}
