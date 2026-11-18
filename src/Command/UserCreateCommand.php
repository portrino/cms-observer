<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Create a dashboard login')]
class UserCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // asked for when missing; the password then without echo and outside the shell history
            ->addArgument('email', InputArgument::OPTIONAL, 'Login email')
            ->addArgument('name', InputArgument::OPTIONAL, 'Display name')
            ->addArgument('password', InputArgument::OPTIONAL, 'Login password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email') ?? $io->ask('Login email');
        $name = $input->getArgument('name') ?? $io->ask('Display name');
        $password = $input->getArgument('password') ?? $io->askHidden('Login password');
        if (!is_string($email) || '' === $email || !is_string($name) || '' === $name || !is_string($password) || '' === $password) {
            $io->error('Email, name and password are required.');

            return Command::FAILURE;
        }

        $user = new User($email, $name);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('User "%s" created.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
