<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes a login, e.g. of someone who left the agency. Nothing else belongs to a user: the
 * authors of calculation versions are text and stay.
 */
#[AsCommand(name: 'app:user:delete', description: 'Delete a dashboard login')]
class UserDeleteCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Login email')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Delete without asking');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $user = $this->userRepository->findOneByEmail($email);
        if (null === $user) {
            $io->error(sprintf('No user "%s".', $email));

            return Command::FAILURE;
        }
        // nobody could log in afterwards; app:user:create first
        if ($this->userRepository->count() <= 1) {
            $io->error('The last login can\'t be deleted, create another one first (app:user:create).');

            return Command::FAILURE;
        }
        if (true !== $input->getOption('force') && !$io->confirm(sprintf('Delete the login "%s" (%s)?', $email, $user->getName()), false)) {
            $io->note('Nothing deleted.');

            return Command::SUCCESS;
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
        // a "Remember me" cookie would otherwise outlive the login; an open session ends on its next request
        $this->connection->executeStatement('DELETE FROM rememberme_token WHERE username = ?', [$user->getUserIdentifier()]);

        $io->success(sprintf('Deleted the login "%s".', $email));

        return Command::SUCCESS;
    }
}
