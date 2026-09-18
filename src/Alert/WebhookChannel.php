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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The warning that reaches somebody who is not looking at the panel.
 *
 * Shaped so that one URL covers the three places these actually go: Slack, Teams and a webhook of
 * one's own. All three accept a JSON body with a `text` field, so `text` carries the sentence and
 * everything else sits beside it for whoever wants to parse it.
 *
 * Two restraints. The payload goes through the same redaction as everything else, because a
 * webhook URL usually belongs to a chat room that outlives the incident. And a webhook that does
 * not answer is logged and forgotten rather than retried — this is called from a scheduled run
 * and from the middle of sending an order confirmation.
 */
final class WebhookChannel
{
    private const TIMEOUT = 5;

    public function __construct(
        private readonly SettingsProvider $settings,
        private readonly Wording $wording,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(Alert $alert, HealthState $state, MailSettings $settings): bool
    {
        $url = $this->settings->webhookUrl();
        if ('' === $url || !str_starts_with($url, 'https://')) {
            // Plain http would put the shop's state on the wire in the clear, and every service
            // that offers webhooks offers them over https.
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'timeout' => self::TIMEOUT,
                'json' => $this->payload($alert, $state, $settings),
            ]);
            $status = $response->getStatusCode();

            if ($status < 200 || $status >= 300) {
                $this->logger->warning('Calmfox SMTP: the webhook answered unhappily.', ['status' => $status]);

                return false;
            }

            return true;
        } catch (\Throwable $error) {
            $this->logger->warning('Calmfox SMTP: the webhook could not be reached.', ['exception' => $error]);

            return false;
        }
    }

    /** @return array<string, mixed> */
    private function payload(Alert $alert, HealthState $state, MailSettings $settings): array
    {
        $explanation = [];
        if ($alert->isProblem()) {
            $explanation = $this->wording->explain($state, $settings);
        }

        return Redaction::applyToArray([
            'text' => $alert->isProblem()
                ? $this->wording->headline($state, $settings)
                : $this->wording->recovery($settings),
            'smtp' => [
                'event' => $alert->kind,
                'reminder' => $alert->isReminder,
                'status' => $state->status,
                'cause' => $state->cause,
                'stage' => $state->stage,
                'reply_code' => $state->replyCode,
                'reply' => $state->reply,
                'provider' => $settings->providerId,
                'endpoint' => $settings->endpoint(),
                'failing_since' => null === $state->failingSince ? null : date(\DATE_ATOM, $state->failingSince),
                'last_ok_at' => null === $state->lastOkAt ? null : date(\DATE_ATOM, $state->lastOkAt),
                'failed_checks' => $state->consecutiveFailures,
                'failed_messages' => $state->sendFailures,
                'explanation' => $explanation,
            ],
        ], array_filter([$settings->password]));
    }
}
