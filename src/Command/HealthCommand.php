<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Command;

use Calmfox\SyliusSmtpPlugin\Core\Health\HealthState;
use Calmfox\SyliusSmtpPlugin\Core\Health\Status;
use Calmfox\SyliusSmtpPlugin\Health\Monitor;
use Calmfox\SyliusSmtpPlugin\Settings\SettingsProvider;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The health check for whatever watches the shop from outside.
 *
 * The exit code is the interface: nought for working, one for a problem that may pass by itself,
 * two for a shop that cannot send, three for one that is not configured to. That is enough for
 * Zabbix, for a systemd timer, for a line in somebody's own crontab, and for a deployment script
 * that would rather not go live with a shop that cannot e-mail anybody.
 *
 * It is also the command to put on a schedule: it is what makes the check periodic.
 */
#[AsCommand(name: 'calmfox:smtp:health', description: 'Checks whether the shop can send e-mail, and says what is wrong if it cannot.')]
final class HealthCommand extends Command
{
    private const EXIT_WARNING = 1;

    private const EXIT_BROKEN = 2;

    private const EXIT_NOT_CONFIGURED = 3;

    public function __construct(
        private readonly Monitor $monitor,
        private readonly SettingsProvider $settings,
        private readonly Wording $wording,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Check now instead of reusing a recent verdict')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the whole verdict as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $resolved = $this->settings->resolve();

        if (!$resolved->settings->enabled) {
            $output->writeln('<comment>' . $this->wording->say('cli.switched_off') . '</comment>');

            return self::EXIT_NOT_CONFIGURED;
        }

        $state = $this->monitor->check(true === $input->getOption('force'));

        if (true === $input->getOption('json')) {
            $safe = $resolved->settings->withoutSecrets();
            $output->writeln((string) json_encode([
                'settings' => [
                    'provider' => $safe->providerId,
                    'endpoint' => $safe->endpoint(),
                    'encryption' => $safe->encryption,
                    'auth' => $safe->authMethod,
                    'username' => $safe->username,
                ],
                'health' => $state->toArray(),
            ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return $this->exitCode($state);
        }

        $output->writeln($this->line($state, $resolved->settings->endpoint()));

        if (Status::OK !== $state->status) {
            foreach ($this->wording->explain($state, $resolved->settings) as $sentence) {
                $output->writeln('  ' . $sentence);
            }
        }
        foreach ($resolved->warnings() as $warning) {
            $output->writeln('  <comment>' . $this->wording->issue($warning) . '</comment>');
        }

        return $this->exitCode($state);
    }

    private function line(HealthState $state, string $endpoint): string
    {
        return match ($state->status) {
            Status::OK => sprintf('<info>%s</info> (%s, %d ms)', $this->wording->status($state->status), $endpoint, $state->durationMs),
            Status::WARN => sprintf('<comment>%s</comment> — %s', $this->wording->status($state->status), $this->wording->cause($state->cause)),
            Status::FAIL => sprintf('<error>%s</error> — %s', $this->wording->status($state->status), $this->wording->cause($state->cause)),
            default => $this->wording->status($state->status),
        };
    }

    private function exitCode(HealthState $state): int
    {
        return match ($state->status) {
            Status::OK => Command::SUCCESS,
            Status::WARN => self::EXIT_WARNING,
            Status::FAIL => self::EXIT_BROKEN,
            default => self::EXIT_NOT_CONFIGURED,
        };
    }
}
