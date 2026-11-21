<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MailTester;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mail:test', description: 'Send a test mail to check the mail setup')]
class MailTestCommand extends Command
{
    public function __construct(private readonly MailTester $mailTester)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('recipient', InputArgument::REQUIRED, 'Email address to send the test mail to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $recipient = (string) $input->getArgument('recipient');

        if (false === filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
            $io->error(sprintf('"%s" is not an email address.', $recipient));

            return Command::FAILURE;
        }

        $io->text(sprintf('Sending from %s via %s', $this->mailTester->getFromAddress(), $this->mailTester->getTransport()));

        $error = $this->mailTester->send($recipient);
        if (null !== $error) {
            $io->error(sprintf('The test mail could not be sent: %s', $error));

            return Command::FAILURE;
        }

        $io->success(sprintf('The mail server accepted the test mail to %s. Check that it arrives, also in the spam folder.', $recipient));

        return Command::SUCCESS;
    }
}
