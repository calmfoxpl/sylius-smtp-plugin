<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Command;

use Calmfox\SyliusSmtpPlugin\Dns\DomainCheck;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reads what the sender domain publishes and keeps the answer, so that no page in the panel ever
 * waits on a nameserver.
 *
 * Put it on a schedule a few times a day. A separate clock from the connection check on purpose:
 * a refused password matters within the quarter hour, while an SPF record that does not list the
 * provider has usually been wrong for weeks and will not be fixed in the next five minutes.
 *
 * The exit code follows the same rule as the health command: the shop can send, so a domain
 * problem is reported and does not turn a monitor red.
 */
#[AsCommand(name: 'calmfox:smtp:dns', description: 'Reads the SPF, DKIM and DMARC records of the domain the shop sends as.')]
final class CheckDomainCommand extends Command
{
    public function __construct(
        private readonly DomainCheck $domains,
        private readonly Wording $wording,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->domains->isEnabled()) {
            $output->writeln('<comment>' . $this->wording->say('dns.switched_off') . '</comment>');

            return Command::SUCCESS;
        }

        $verdict = $this->domains->refresh();
        if (null === $verdict) {
            return Command::SUCCESS;
        }

        $output->writeln($verdict->isDeliverabilityAtRisk()
            ? '<comment>' . $this->wording->deliverabilityHeadline($verdict->domain) . '</comment>'
            : '<info>' . $this->wording->deliverabilityFine($verdict->domain) . '</info>');

        foreach ($verdict->issues as $issue) {
            $output->writeln('  ' . $this->wording->issue($issue));
        }

        return Command::SUCCESS;
    }
}
