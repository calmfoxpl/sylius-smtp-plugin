<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Controller\Admin;

use Calmfox\SyliusSmtpPlugin\Mailer\TestSender;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sends one test message and reports what the server said about it.
 *
 * The success wording is careful on purpose. "Accepted by the server" is all we know, and a
 * plugin that answers "sent successfully" teaches shopkeepers to trust a green tick that cannot
 * see a spam folder. So it says what happened, and then says where to look.
 */
final readonly class TestAction
{
    public function __construct(
        private TestSender $sender,
        private SettingsProvider $settings,
        private Wording $wording,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $recipient = trim((string) ($request->request->get('recipient') ?: $this->settings->alertRecipient()));

        if ('' === $recipient) {
            return new JsonResponse([
                'ok' => false,
                'headline' => $this->wording->say('test.nobody'),
                'lines' => [$this->wording->say('test.nobody_hint')],
            ]);
        }

        try {
            $milliseconds = $this->sender->send($recipient);
        } catch (\Throwable $failure) {
            return new JsonResponse([
                'ok' => false,
                'headline' => $this->wording->say('test.not_sent'),
                'lines' => [$failure->getMessage()],
            ]);
        }

        return new JsonResponse([
            'ok' => true,
            'headline' => $this->wording->say('test.accepted', ['%recipient%' => $recipient, '%ms%' => (string) $milliseconds]),
            'lines' => [
                $this->wording->say('test.look_now'),
                $this->wording->say('test.if_spam'),
                $this->wording->say('test.if_nowhere'),
            ],
        ]);
    }
}
