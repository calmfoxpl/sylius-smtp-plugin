<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Log;

use Calmfox\SyliusSmtpPlugin\Core\Diagnosis\Diagnosis;
use Calmfox\SyliusSmtpPlugin\Entity\EmailLogEntry;
use Calmfox\SyliusSmtpPlugin\Health\Monitor;
use Calmfox\SyliusSmtpPlugin\Mailer\TransportBuilder;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * Sends a failed message again, from the panel.
 *
 * The case this exists for is the ordinary one: an API key expired at nine, somebody noticed at
 * eleven, and the eleven order confirmations in between are in the log. Fixing the key does not
 * send them, and asking customers to order again is not a plan.
 *
 * The stored message goes out verbatim — the same headers, the same body, the same envelope —
 * through whatever the settings say now, which is the point: what changed is the settings.
 */
final class Resender
{
    public function __construct(
        private readonly SettingsProvider $settings,
        private readonly TransportBuilder $builder,
        private readonly Writer $writer,
        private readonly Monitor $monitor,
        private readonly Wording $wording,
    ) {
    }

    /** @throws ResendFailed when there is nothing to send, or the settings cannot send it */
    public function resend(EmailLogEntry $entry): void
    {
        if (!$entry->canBeResent()) {
            throw new ResendFailed($this->wording->say('resend.not_kept'));
        }

        $resolved = $this->settings->resolve();
        if (!$resolved->isUsable()) {
            throw new ResendFailed($this->wording->say('resend.settings_broken'));
        }

        $settings = $resolved->settings;
        $secrets = array_values(array_filter([$settings->password]));
        $envelope = new Envelope(
            new Address($entry->getEnvelopeSender() ?? $entry->getFromAddress()),
            array_map(static fn (string $address): Address => new Address($address), $entry->getEnvelopeRecipients()),
        );

        try {
            $this->builder->build($settings)->send(new RawMessage((string) $entry->getRawMessage()), $envelope);
        } catch (\Throwable $failure) {
            $cause = Diagnosis::fromSendFailure($failure->getMessage());
            $this->writer->recordResend($entry, false, $cause, $failure->getMessage(), $secrets);
            $this->monitor->recordSendFailure($cause, $failure->getMessage());

            throw new ResendFailed(
                $this->wording->say('resend.failed_again', ['%error%' => $failure->getMessage()]),
                previous: $failure,
            );
        }

        $this->writer->recordResend($entry, true, null, '', $secrets);
        $this->monitor->recordSendSuccess();
    }
}
