<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Log;

use Calmfox\SyliusSmtpPlugin\Core\Log\MessageSummary;
use Calmfox\SyliusSmtpPlugin\Core\Log\Outcome;
use Calmfox\SyliusSmtpPlugin\Core\Log\Redaction;
use Calmfox\SyliusSmtpPlugin\Core\Log\Retention;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;
use Calmfox\SyliusSmtpPlugin\Entity\EmailLogEntry;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Writes the log row, and never at the expense of the message.
 *
 * Two rules. Nothing in here may throw: the caller is a mail transport in the middle of sending
 * an order confirmation, and a full disk or a missing table must not become a lost e-mail. And
 * the password never reaches the table — not even through the error column, which is where a
 * failed SMTP conversation would otherwise leave a base64 copy of it.
 */
final class Writer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(
        MailSettings $settings,
        string $rawMessage,
        string $outcome,
        int $durationMs,
        ?string $cause = null,
        string $error = '',
        bool $keepBody = false,
    ): ?EmailLogEntry {
        try {
            $summary = MessageSummary::fromRaw($rawMessage);
            $secrets = array_values(array_filter([$settings->password]));

            $entry = new EmailLogEntry();
            $entry->setOutcome($outcome);
            $entry->setProvider($settings->providerId);
            $entry->setEndpoint($settings->endpoint());
            $entry->setFromAddress($summary->from);
            $entry->setRecipients($summary->recipientsForColumn());
            $entry->setSubject($summary->subjectForColumn());
            $entry->setMessageSize($summary->bytes);
            $entry->setDurationMs($durationMs);
            $entry->setCause($cause);
            $entry->setError('' === $error ? null : Redaction::apply($error, $secrets));
            if (Outcome::FAILED === $entry->getOutcome() && Retention::shouldStoreBody($keepBody, $summary->bytes)) {
                // The full address list and the message body are kept for one reason: sending it
                // again. A message that went out needs neither, so a shop's log does not quietly
                // accumulate every recipient of every order confirmation it ever sent.
                $entry->setRawMessage(Redaction::apply($rawMessage, $secrets));
                $entry->setEnvelopeSender($settings->returnPath ?? $settings->fromEmail ?? $summary->from);
                $entry->setEnvelopeRecipients($summary->recipients);
            }

            $this->entityManager->persist($entry);
            $this->entityManager->flush();

            return $entry;
        } catch (\Throwable $failure) {
            // The log is a convenience. The message is the job.
            $this->logger->warning('Calmfox SMTP: a message could not be logged.', ['exception' => $failure]);

            return null;
        }
    }

    /** A message sent again from the panel: the same row, one more attempt. */
    public function recordResend(EmailLogEntry $entry, bool $sent, ?string $cause, string $error, array $secrets = []): void
    {
        try {
            $entry->setOutcome($sent ? Outcome::SENT : Outcome::FAILED);
            $entry->anotherAttempt();
            $entry->setCause($sent ? null : $cause);
            $entry->setError($sent || '' === $error ? null : Redaction::apply($error, $secrets));

            if ($sent) {
                // It has gone. What is left is personal data with nothing left to do.
                $entry->forgetBody();
            }

            $this->entityManager->persist($entry);
            $this->entityManager->flush();
        } catch (\Throwable $failure) {
            $this->logger->warning('Calmfox SMTP: a resend could not be recorded.', ['exception' => $failure]);
        }
    }
}
