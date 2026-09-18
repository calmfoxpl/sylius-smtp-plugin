<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Controller\Admin;

use Calmfox\SyliusSmtpPlugin\Log\LogRepository;
use Calmfox\SyliusSmtpPlugin\Log\Resender;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends a failed message again.
 *
 * A POST, because it puts real e-mail in front of real customers, and reached only from the
 * entry's own page rather than from a list — where the row under the cursor is not always the row
 * that was meant.
 */
final readonly class LogResendAction
{
    public function __construct(
        private LogRepository $entries,
        private Resender $resender,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, int $id): RedirectResponse
    {
        $entry = $this->entries->find($id);
        $session = $request->getSession();

        if (null === $entry) {
            return new RedirectResponse($this->urlGenerator->generate('calmfox_smtp_log'));
        }

        try {
            $this->resender->resend($entry);
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('success', 'calmfox_smtp.flash.resent');
            }
        } catch (\Throwable $failure) {
            if ($session instanceof FlashBagAwareSessionInterface) {
                // Already a sentence fit to show: the resender words its own refusals.
                $session->getFlashBag()->add('error', $failure->getMessage());
            }
        }

        return new RedirectResponse($this->urlGenerator->generate('calmfox_smtp_log_show', ['id' => $id]));
    }
}
