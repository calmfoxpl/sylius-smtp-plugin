<?php

declare(strict_types=1);

namespace Calmfox\SyliusSmtpPlugin\Command;

use Calmfox\SyliusSmtpPlugin\Mailer\TestSender;
use Calmfox\SyliusSmtpPlugin\Text\Wording;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Sends one real message, for when the question is delivery rather than connection. */
#[AsCommand(name: 'calmfox:smtp:test', description: 'Sends a test message through the configured server.')]
final class TestCommand extends Command
{
    public function __construct(
        private readonly TestSender $sender,
        private readonly Wording $wording,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('recipient', InputArgument::REQUIRED, 'Where to send it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $recipient = (string) $input->getArgument('recipient');

        try {
            $milliseconds = $this->sender->send($recipient);
        } catch (\Throwable $failure) {
            $output->writeln('<error>' . $failure->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>' . $this->wording->say('test.accepted', [
            '%recipient%' => $recipient,
            '%ms%' => (string) $milliseconds,
        ]) . '</info>');

        return Command::SUCCESS;
    }
}
