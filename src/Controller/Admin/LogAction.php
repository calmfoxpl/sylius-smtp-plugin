<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Controller\Admin;

use Calmfox\SyliusSmtpPlugin\Log\LogRepository;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The list of what the shop tried to send.
 *
 * Newest first, with the two filters people actually arrive with: "did this address get
 * anything?" and "what failed today?". A plain paged list rather than a grid, because the page
 * has one job and a grid would bring a configuration surface with it.
 */
final readonly class LogAction
{
    public function __construct(
        private LogRepository $entries,
        private SettingsProvider $settings,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $page = $this->entries->page(
            (int) $request->query->get('page', '1'),
            (string) $request->query->get('outcome', ''),
            (string) $request->query->get('q', ''),
        );

        return new Response($this->twig->render('@CalmfoxSyliusSmtpPlugin/admin/log/index.html.twig', [
            'page' => $page,
            'outcome' => (string) $request->query->get('outcome', ''),
            'search' => (string) $request->query->get('q', ''),
            'log_enabled' => $this->settings->logEnabled(),
            'retention_days' => $this->settings->retentionDays(),
        ]));
    }
}
