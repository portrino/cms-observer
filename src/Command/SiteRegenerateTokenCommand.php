<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SiteRepository;
use App\Security\SiteTokenFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:site:regenerate-token', description: 'Replace a site\'s push token and print the new one')]
class SiteRegenerateTokenCommand extends Command
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteTokenFactory $tokenFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Site ID, see app:site:list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = (string) $input->getArgument('id');
        $site = ctype_digit($id) ? $this->siteRepository->find((int) $id) : null;
        if (null === $site) {
            $io->error(sprintf('No site found with ID "%s".', $id));

            return Command::FAILURE;
        }

        if (!$io->confirm(sprintf('The current token of "%s" stops working immediately. Continue?', $site->getName()))) {
            return Command::FAILURE;
        }

        [$token, $tokenHash] = $this->tokenFactory->generate();
        $site->replaceTokenHash($tokenHash);
        $this->entityManager->flush();

        $io->success(sprintf('New token generated for "%s" (ID: %d).', $site->getName(), $site->getId()));
        $io->warning('Copy this token now, it will not be shown again:');
        $io->writeln($token);

        return Command::SUCCESS;
    }
}
