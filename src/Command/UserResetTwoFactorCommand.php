<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Last resort when someone lost both the authenticator app and the backup
 * codes (or TOTP_ENCRYPTION_KEY was lost) - server access only, no web route.
 */
#[AsCommand(name: 'app:user:reset-2fa', description: 'Disable two-factor authentication for a user who lost access to it')]
class UserResetTwoFactorCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Login email');
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

        if (!$user->isTotpAuthenticationEnabled()) {
            $io->note(sprintf('Two-factor authentication is not enabled for "%s".', $email));

            return Command::SUCCESS;
        }

        $user->disableTwoFactor();
        $this->entityManager->flush();

        $io->success(sprintf('Two-factor authentication disabled for "%s". They can set it up again on their profile page.', $email));

        return Command::SUCCESS;
    }
}
