<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Controller\Admin;

use Calmfox\SyliusSmtpPlugin\Core\Health\Status;
use Calmfox\SyliusSmtpPlugin\Health\Monitor;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Checks now, and says everything it found.
 *
 * Always a fresh check: this is a button somebody pressed, and "now" is what they meant. The
 * answer is a headline and a list of sentences rather than a status code, because what makes the
 * button worth pressing is the sentence after the failure — the one naming the thing to go and
 * look at. The settings warnings come with it, which is where they are most useful.
 */
final readonly class CheckAction
{
    public function __construct(
        private Monitor $monitor,
        private SettingsProvider $settings,
        private Wording $wording,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $resolved = $this->settings->resolve();
        $lines = [];

        foreach ($resolved->issues as $issue) {
            $lines[] = $this->wording->issue($issue);
        }

        if (!$resolved->settings->enabled) {
            return new JsonResponse([
                'ok' => false,
                'headline' => $this->wording->say('cli.switched_off'),
                'lines' => $lines,
            ]);
        }

        $state = $this->monitor->check(true);
        $ok = Status::OK === $state->status;

        foreach ($this->wording->explain($state, $resolved->settings) as $sentence) {
            $lines[] = $sentence;
        }
        if ($ok) {
            $lines[] = $this->wording->say('report.checked_in', ['%ms%' => (string) $state->durationMs]);
        }

        return new JsonResponse([
            'ok' => $ok,
            'headline' => $this->wording->headline($state, $resolved->settings),
            'lines' => array_values(array_unique($lines)),
        ]);
    }
}
