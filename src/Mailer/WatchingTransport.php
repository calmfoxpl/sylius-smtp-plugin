<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Mailer;

use Calmfox\SyliusSmtpPlugin\Core\Diagnosis\Diagnosis;
use Calmfox\SyliusSmtpPlugin\Core\Log\Outcome;
use Calmfox\SyliusSmtpPlugin\Core\Log\Redaction;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;
use Calmfox\SyliusSmtpPlugin\Health\Monitor;
use Calmfox\SyliusSmtpPlugin\Log\Writer;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * The transport every outgoing message passes through.
 *
 * It decorates Symfony's default transport rather than replacing it, and that is the contract:
 * with the plugin switched off, or configured too incompletely to send, the application's own
 * `MAILER_DSN` does exactly what it did before. Uninstalling is then a setting rather than a
 * deployment, and a shop that has always sent through a DSN keeps doing so until somebody
 * deliberately fills in the panel.
 *
 * Decorating the transport rather than the mailer is deliberate too: messages sent through
 * Messenger reach the transport and not the mailer, and an order confirmation queued for later
 * is exactly the kind of message worth watching.
 *
 * Nothing about recording may cost a message. A failure to write the log row is a line in the
 * application log; the exception that reaches Sylius is only ever the one about the message.
 */
final class WatchingTransport implements TransportInterface
{
    public function __construct(
        private readonly TransportInterface $inner,
        private readonly SettingsProvider $settings,
        private readonly TransportBuilder $builder,
        private readonly Writer $writer,
        private readonly Monitor $monitor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $resolved = $this->settings->resolve();
        if (!$resolved->isUsable()) {
            return $this->inner->send($message, $envelope);
        }

        $settings = $resolved->settings;
        $message = $this->withSender($message, $settings);
        $envelope = $this->envelopeFor($message, $envelope, $settings);
        $raw = $message->toString();
        $started = microtime(true);

        try {
            $sent = $this->builder->build($settings)->send($message, $envelope);
        } catch (\Throwable $failure) {
            $this->afterFailure($settings, $raw, $failure, self::since($started));

            throw $failure;
        }

        $this->afterSuccess($settings, $raw, self::since($started));

        return $sent;
    }

    public function __toString(): string
    {
        $resolved = $this->settings->resolve();

        return $resolved->isUsable()
            ? 'calmfox+smtp://' . $resolved->settings->endpoint()
            : (string) $this->inner;
    }

    /**
     * The sender, overridden where that was asked for.
     *
     * Providers reject mail whose sender is not one of theirs, and a Sylius installation has
     * sender addresses in the channel settings, in e-mail configuration and in whatever a plugin
     * decided. Forcing one here is the difference between a shop that sends and an afternoon of
     * finding out which of them was used.
     */
    private function withSender(RawMessage $message, MailSettings $settings): RawMessage
    {
        if (!$message instanceof Email) {
            // A raw message is already serialised; rewriting its headers would be guesswork.
            return $message;
        }

        if (null !== $settings->fromEmail) {
            return $message->from(new Address($settings->fromEmail, $settings->fromName ?? ''));
        }

        if (null !== $settings->fromName) {
            $from = $message->getFrom();
            if ([] !== $from) {
                return $message->from(new Address($from[0]->getAddress(), $settings->fromName));
            }
        }

        return $message;
    }

    /**
     * The envelope sender is where bounces go, and it is the address providers actually check —
     * a different thing from the From header, and worth being able to set on its own.
     */
    private function envelopeFor(RawMessage $message, ?Envelope $envelope, MailSettings $settings): ?Envelope
    {
        if (null === $settings->returnPath) {
            return $envelope;
        }

        try {
            $envelope ??= Envelope::create($message);

            return new Envelope(new Address($settings->returnPath), $envelope->getRecipients());
        } catch (\Throwable $error) {
            $this->logger->warning('Calmfox SMTP: the envelope sender could not be applied.', ['exception' => $error]);

            return $envelope;
        }
    }

    private function afterSuccess(MailSettings $settings, string $raw, int $milliseconds): void
    {
        $this->guard(function () use ($settings, $raw, $milliseconds): void {
            if ($this->settings->logEnabled()) {
                $this->writer->record($settings, $raw, Outcome::SENT, $milliseconds);
            }
            $this->monitor->recordSendSuccess();
        });
    }

    private function afterFailure(MailSettings $settings, string $raw, \Throwable $failure, int $milliseconds): void
    {
        $cause = Diagnosis::fromSendFailure($failure->getMessage());

        $this->guard(function () use ($settings, $raw, $failure, $milliseconds, $cause): void {
            if ($this->settings->logEnabled()) {
                $this->writer->record(
                    $settings,
                    $raw,
                    Outcome::FAILED,
                    $milliseconds,
                    $cause,
                    $failure->getMessage(),
                    $this->settings->logsBody(),
                );
            }
            $this->monitor->recordSendFailure($cause, $failure->getMessage());
        });

        $this->logger->error('Calmfox SMTP: a message could not be sent.', [
            'endpoint' => $settings->endpoint(),
            'error' => Redaction::apply($failure->getMessage(), [$settings->password]),
        ]);
    }

    /** Bookkeeping must never be the reason a shop stops sending. */
    private function guard(callable $work): void
    {
        try {
            $work();
        } catch (\Throwable $error) {
            $this->logger->warning('Calmfox SMTP: a send could not be recorded.', ['exception' => $error]);
        }
    }

    private static function since(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
