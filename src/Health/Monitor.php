<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Health;

use Calmfox\SyliusSmtpPlugin\Alert\Notifier;
use Calmfox\SyliusSmtpPlugin\Core\Alert\Alert;
use Calmfox\SyliusSmtpPlugin\Core\Alert\AlertPolicy;
use Calmfox\SyliusSmtpPlugin\Core\Health\HealthEvaluator;
use Calmfox\SyliusSmtpPlugin\Core\Health\HealthState;
use Calmfox\SyliusSmtpPlugin\Core\Settings\MailSettings;
use Calmfox\SyliusSmtpPlugin\Probe\SmtpProbe;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Psr\Log\LoggerInterface;

/**
 * The health check as the rest of the plugin sees it: check, remember, and warn once.
 *
 * The orchestration is here and the judgement is in the framework-free core, which is why this
 * class has no decision in it worth arguing about. Two things are worth knowing: a verdict is
 * reused for as long as the settings say, so a dashboard, a scheduled run and a console command
 * in the same minute produce one connection rather than three — and `force` is for the button
 * somebody pressed, which always means now.
 */
final class Monitor
{
    public function __construct(
        private readonly SettingsProvider $settings,
        private readonly SmtpProbe $probe,
        private readonly HealthStore $store,
        private readonly Notifier $notifier,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function state(): HealthState
    {
        return $this->store->load();
    }

    public function check(bool $force = false): HealthState
    {
        $resolved = $this->settings->resolve();
        $settings = $resolved->settings;
        $state = $this->store->load();

        if (!$settings->enabled || !$this->settings->healthCheckEnabled()) {
            return $state;
        }
        if (!$force && $this->isFresh($state, $settings)) {
            return $state;
        }

        $evaluator = new HealthEvaluator($this->settings->thresholds());
        $now = time();

        $state = $resolved->isUsable()
            ? $evaluator->afterProbe($state, $this->probe->run($settings), $settings, $now)
            : $evaluator->afterUnusableSettings($state, $resolved, $now);

        $this->store->save($state);

        return $this->announce($state, $settings, $now);
    }

    public function recordSendFailure(string $cause, string $message): HealthState
    {
        $settings = $this->settings->resolve()->settings;
        $now = time();

        $state = (new HealthEvaluator($this->settings->thresholds()))
            ->afterSendFailure($this->store->load(), $cause, $message, $now);
        $this->store->save($state);

        return $this->announce($state, $settings, $now);
    }

    public function recordSendSuccess(): HealthState
    {
        $previous = $this->store->load();
        $now = time();

        // A shop that is sending normally would otherwise write this row on every message. The
        // record only needs touching when it has something new to say.
        if (!$previous->isBroken() && 0 === $previous->sendFailures && null !== $previous->lastSentAt && $now - $previous->lastSentAt <= 3600) {
            return $previous;
        }

        $state = (new HealthEvaluator($this->settings->thresholds()))->afterSendSuccess($previous, $now);
        $this->store->save($state);

        return $this->announce($state, $this->settings->resolve()->settings, $now);
    }

    /**
     * Tell whoever asked to be told, once, and write down that we did.
     *
     * The writing down is the part that matters: without it the plugin either repeats itself every
     * quarter of an hour or forgets to mention that a problem is over.
     */
    private function announce(HealthState $state, MailSettings $settings, int $now): HealthState
    {
        $alert = (new AlertPolicy($this->settings->thresholds()))->decide($state, $now);
        if (!$alert->shouldSpeak()) {
            return $state;
        }

        try {
            $this->notifier->dispatch($alert, $state, $settings);
        } catch (\Throwable $error) {
            $this->logger->warning('Calmfox SMTP: the warning could not be delivered.', ['exception' => $error]);
        }

        // Marked as told whether or not a channel accepted it: the panel shows the state either
        // way, and retrying a dead local mail command on every run helps nobody.
        $state = Alert::RECOVERY === $alert->kind ? $state->forgotten() : $state->notified($alert->cause, $now);
        $this->store->save($state);

        return $state;
    }

    private function isFresh(HealthState $state, MailSettings $settings): bool
    {
        if (!$state->wasEverChecked() || $state->fingerprint !== $settings->fingerprint()) {
            return false;
        }

        return time() - $state->checkedAt < $this->settings->checkEverySeconds();
    }
}
