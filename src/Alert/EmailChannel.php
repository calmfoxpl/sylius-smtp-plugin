<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Alert;

use Calmfox\SyliusSmtpPlugin\Core\Alert\Alert;
use Calmfox\SyliusSmtpPlugin\Core\Health\HealthState;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Settings\ShopName;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * The warning that leaves the building, sent deliberately the wrong way.
 *
 * There is an obvious problem with e-mailing somebody to tell them their e-mail is broken, and
 * the only honest answer is not to use the broken path: this goes out through the server's own
 * local mail command, not the configured provider and not the application's mailer — which is,
 * after all, us.
 *
 * That has a cost, and the panel says so rather than pretending otherwise: local mail is often
 * unconfigured, and when it is configured it often lands in a spam folder. It is a second chance,
 * not a guarantee, which is why the panel notice and the webhook exist alongside it.
 */
final class EmailChannel
{
    public function __construct(
        private readonly SettingsProvider $settings,
        private readonly Wording $wording,
        private readonly LoggerInterface $logger,
        private readonly ShopName $shopName,
    ) {
    }

    public function send(Alert $alert, HealthState $state, MailSettings $settings): bool
    {
        $recipient = $this->settings->alertRecipient();
        if ('' === $recipient) {
            return false;
        }

        try {
            $email = (new Email())
                ->from(new Address($recipient, $this->shopName->get()))
                ->to(new Address($recipient))
                ->subject($this->subject($alert, $state))
                ->text($this->body($alert, $state, $settings));

            (new SendmailTransport())->send($email);

            return true;
        } catch (\Throwable $error) {
            // Expected on a server with no local mail command. The panel and the log still have it.
            $this->logger->warning('Calmfox SMTP: the alert e-mail could not be sent.', ['exception' => $error]);

            return false;
        }
    }

    private function subject(Alert $alert, HealthState $state): string
    {
        return $alert->isProblem()
            ? $this->wording->say('alert.subject.problem', ['%shop%' => $this->shopName->get(), '%cause%' => $this->wording->cause($state->cause)])
            : $this->wording->say('alert.subject.recovered', ['%shop%' => $this->shopName->get()]);
    }

    private function body(Alert $alert, HealthState $state, MailSettings $settings): string
    {
        if (!$alert->isProblem()) {
            return implode("\n", [
                $this->wording->recovery($settings),
                '',
                $this->wording->say('alert.nothing_to_do'),
            ]) . "\n";
        }

        $lines = [$this->wording->headline($state, $settings), ''];
        foreach ($this->wording->explain($state, $settings) as $sentence) {
            $lines[] = $sentence;
        }
        $lines[] = '';

        if (null !== $state->failingSince) {
            $lines[] = $this->wording->say('alert.failing_since', ['%since%' => date('Y-m-d H:i', $state->failingSince)]);
        }
        if ($state->sendFailures > 0) {
            $lines[] = $this->wording->say('alert.failed_messages', ['%count%' => (string) $state->sendFailures]);
        }

        $lines[] = '';
        $lines[] = $this->wording->say('alert.sent_locally');

        return implode("\n", $lines) . "\n";
    }
}
