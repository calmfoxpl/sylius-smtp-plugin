<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Controller\Admin;

use Calmfox\SyliusSmtpPlugin\Log\LogRepository;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * One message from the log, in full.
 *
 * The page exists for the conversation that starts "the customer says they never got it". It
 * shows what was sent, where it went, what the server answered, and — where the message is still
 * stored — offers to send it again. The body is shown as source rather than rendered: this is a
 * page for finding out what happened, and rendering somebody else's HTML inside an admin panel is
 * a habit worth not having.
 */
final readonly class LogShowAction
{
    public function __construct(
        private LogRepository $entries,
        private SettingsProvider $settings,
        private Wording $wording,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {
    }

    public function __invoke(int $id): Response
    {
        $entry = $this->entries->find($id);
        if (null === $entry) {
            return new RedirectResponse($this->urlGenerator->generate('calmfox_smtp_log'));
        }

        $lines = [];
        if ($entry->hasFailed()) {
            if (null !== $entry->getCause()) {
                $lines[] = $this->wording->cause($entry->getCause());
            }
            if (null !== $entry->getError()) {
                $lines[] = $this->wording->say('server_said', ['%reply%' => $entry->getError()]);
            }
        }

        return new Response($this->twig->render('@CalmfoxSyliusSmtpPlugin/admin/log/show.html.twig', [
            'entry' => $entry,
            'failure_lines' => $lines,
            'can_resend' => $entry->canBeResent(),
            'resend_note' => $this->resendNote($entry->hasFailed(), $entry->canBeResent()),
        ]));
    }

    /** Why the button is not there, which is more use than no button and no explanation. */
    private function resendNote(bool $failed, bool $canResend): string
    {
        if (!$failed) {
            return '';
        }
        if ($canResend) {
            return $this->wording->say('resend.explained');
        }

        return $this->settings->logsBody()
            ? $this->wording->say('resend.too_large')
            : $this->wording->say('resend.bodies_not_kept');
    }
}
