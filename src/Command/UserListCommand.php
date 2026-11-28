<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:list', description: 'List the dashboard logins')]
class UserListCommand extends Command
{
    public function __construct(private readonly UserRepository $userRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = array_map(static fn (User $user): array => [
            $user->getId(),
            $user->getEmail(),
            $user->getName(),
            // by the date it was set up: telling "enabled" needs no decryption of the secret
            null !== $user->getTwoFactorEnabledAt() ? 'yes' : 'no',
            $user->getLocale() ?? 'browser',
        ], $this->userRepository->findBy([], ['id' => 'ASC']));

        new SymfonyStyle($input, $output)->table(['ID', 'Email', 'Name', '2FA', 'Language'], $rows);

        return Command::SUCCESS;
    }
}
