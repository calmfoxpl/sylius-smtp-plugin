<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Mailer;

use Calmfox\SyliusSmtpPlugin\Core\Diagnosis\Diagnosis;
use Calmfox\SyliusSmtpPlugin\Core\Log\Outcome;
use Calmfox\SyliusSmtpPlugin\Health\Monitor;
use Calmfox\SyliusSmtpPlugin\Log\Writer;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * A real message, sent on purpose, because a connection check is not a delivery check.
 *
 * The health check deliberately stops before `MAIL FROM`, so it cannot say whether a message
 * would arrive — and the failures it cannot see are real ones: a sender address the provider has
 * not verified, an account still in a sandbox, a domain with no SPF record whose mail is accepted
 * and then dropped. So the panel has a button that sends one message when a person asks for it,
 * and the message says where to look next.
 *
 * It goes through the same builder as everything else. A test that took a different path would be
 * a test of the wrong thing.
 */
final class TestSender
{
    public function __construct(
        private readonly SettingsProvider $settings,
        private readonly TransportBuilder $builder,
        private readonly Writer $writer,
        private readonly Monitor $monitor,
        private readonly Wording $wording,
    ) {
    }

    /**
     * @throws TestFailed
     *
     * @return int how long the whole thing took, in milliseconds
     */
    public function send(string $recipient): int
    {
        if (false === filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
            throw new TestFailed($this->wording->say('issue.from_email_invalid', ['%1%' => $recipient]));
        }

        $resolved = $this->settings->resolve();
        if (!$resolved->isUsable()) {
            throw new TestFailed($this->wording->say('test.not_configured'));
        }

        $settings = $resolved->settings;
        $sender = $settings->fromEmail ?? $settings->username;
        if ('' === $sender) {
            throw new TestFailed($this->wording->say('test.no_sender'));
        }

        $email = (new Email())
            ->from(new Address($sender, $settings->fromName ?? ''))
            ->to(new Address($recipient))
            ->subject($this->wording->say('test.subject'))
            ->text(implode("\n", [
                $this->wording->say('test.body.intro'),
                '',
                $this->wording->say('test.body.through', ['%endpoint%' => $settings->endpoint()]),
                $this->wording->say('test.body.sender', ['%sender%' => $sender]),
                '',
                $this->wording->say('test.body.spam'),
            ]) . "\n");

        $raw = $email->toString();
        $started = microtime(true);

        try {
            $this->builder->build($settings)->send($email);
        } catch (\Throwable $failure) {
            $milliseconds = self::since($started);
            $cause = Diagnosis::fromSendFailure($failure->getMessage());

            if ($this->settings->logEnabled()) {
                $this->writer->record($settings, $raw, Outcome::FAILED, $milliseconds, $cause, $failure->getMessage());
            }
            $this->monitor->recordSendFailure($cause, $failure->getMessage());

            throw new TestFailed(
                $this->wording->say('test.refused', ['%error%' => $failure->getMessage()]),
                previous: $failure,
            );
        }

        $milliseconds = self::since($started);
        if ($this->settings->logEnabled()) {
            $this->writer->record($settings, $raw, Outcome::SENT, $milliseconds);
        }
        $this->monitor->recordSendSuccess();

        return $milliseconds;
    }

    private static function since(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
