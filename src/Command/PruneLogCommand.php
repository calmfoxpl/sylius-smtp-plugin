<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Command;

use Calmfox\SyliusSmtpPlugin\Log\Pruner;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Empties the log by hand, which is what somebody wants on the day they realise how much customer
 * correspondence is sitting in it. Put it on a daily schedule to keep the retention honest.
 */
#[AsCommand(name: 'calmfox:smtp:log:prune', description: 'Deletes log entries older than the retention, or older than --days.')]
final class PruneLogCommand extends Command
{
    public function __construct(
        private readonly Pruner $pruner,
        private readonly Wording $wording,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Keep this many days instead of what the settings say')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Delete every entry');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = true === $input->getOption('all') ? 0 : $input->getOption('days');
        $removed = $this->pruner->prune(null === $days ? null : max(0, (int) $days));

        $output->writeln($this->wording->say('cli.entries_deleted', ['%count%' => (string) $removed]));

        return Command::SUCCESS;
    }
}
