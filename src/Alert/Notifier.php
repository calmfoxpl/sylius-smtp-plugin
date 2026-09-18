<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Alert;

use Calmfox\SyliusSmtpPlugin\Core\Alert\Alert;
use Calmfox\SyliusSmtpPlugin\Core\Health\HealthState;
use Calmfox\SyliusSmtpPlugin\Core\Log\Redaction;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Psr\Log\LoggerInterface;

/**
 * Says the thing, everywhere it was asked to be said.
 *
 * The four channels are not alternatives, they are four different people: the administrator who
 * will open the panel this afternoon (the notice, which needs nothing from this class — it reads
 * the health record), whoever gets the e-mail, whichever chat room the webhook points at, and the
 * monitoring system that watches the application log or runs the console command.
 *
 * A channel that fails is logged and stepped over. The point of having four is that the warning
 * survives one of them being broken — and on a shop whose mail is down, one of them is broken by
 * definition.
 */
final class Notifier
{
    public function __construct(
        private readonly SettingsProvider $settings,
        private readonly Wording $wording,
        private readonly EmailChannel $email,
        private readonly WebhookChannel $webhook,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return list<string> the channels that accepted the warning */
    public function dispatch(Alert $alert, HealthState $state, MailSettings $settings): array
    {
        if (!$alert->shouldSpeak()) {
            return [];
        }

        $this->log($alert, $state, $settings);
        $delivered = ['log'];

        if ($this->settings->warnsByEmail() && $this->email->send($alert, $state, $settings)) {
            $delivered[] = 'email';
        }
        if ($this->settings->warnsByWebhook() && $this->webhook->send($alert, $state, $settings)) {
            $delivered[] = 'webhook';
        }
        if ($this->settings->warnsInPanel()) {
            // Nothing to send: the notice is drawn from the health record, which is already
            // written by the time we get here.
            $delivered[] = 'panel';
        }

        return $delivered;
    }

    /**
     * The line in the application log, which is the channel an outside monitor watches.
     *
     * At `critical` for a problem, because that is the level log shippers alert on, and at `info`
     * for a recovery, because nobody should be paged to be told something is fine.
     */
    private function log(Alert $alert, HealthState $state, MailSettings $settings): void
    {
        $context = Redaction::applyToArray([
            'status' => $state->status,
            'cause' => $state->cause,
            'stage' => $state->stage,
            'endpoint' => $settings->endpoint(),
            'provider' => $settings->providerId,
            'reply' => $state->reply,
            'reminder' => $alert->isReminder,
        ], array_filter([$settings->password]));

        if ($alert->isProblem()) {
            $this->logger->critical($this->wording->headline($state, $settings), $context);

            return;
        }

        $this->logger->info($this->wording->recovery($settings), $context);
    }
}
