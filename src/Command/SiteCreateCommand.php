<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Repository\OrganizationRepository;
use App\Security\SiteTokenFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:site:create', description: 'Register a monitored site and print its push token')]
class SiteCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteTokenFactory $tokenFactory,
        private readonly OrganizationRepository $organizationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Human-readable site name')
            ->addArgument('type', InputArgument::REQUIRED, 'CMS type: '.implode('|', array_column(CmsType::cases(), 'value')))
            ->addArgument('url', InputArgument::REQUIRED, 'Site base URL')
            ->addOption('sla', null, InputOption::VALUE_NONE, 'Mark this site as having an active maintenance contract (SLA)')
            ->addOption('organization', null, InputOption::VALUE_REQUIRED, 'Organization ID to attach this site to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = CmsType::tryFrom((string) $input->getArgument('type'));
        if (null === $type) {
            $io->error(sprintf('Invalid type. Allowed: %s', implode(', ', array_column(CmsType::cases(), 'value'))));

            return Command::FAILURE;
        }

        $url = (string) $input->getArgument('url');

        $organization = null;
        $organizationId = $input->getOption('organization');
        if (null !== $organizationId) {
            $organization = $this->organizationRepository->find((int) $organizationId);
            if (null === $organization) {
                $io->error(sprintf('No organization found with ID "%s".', $organizationId));

                return Command::FAILURE;
            }
        }

        [$token, $tokenHash] = $this->tokenFactory->generate();

        $site = new Site(
            (string) $input->getArgument('name'),
            $type,
            $url,
            $tokenHash,
            (bool) $input->getOption('sla'),
            $organization,
        );

        $this->entityManager->persist($site);
        $this->entityManager->flush();

        $io->success(sprintf('Site "%s" registered (ID: %d).', $site->getName(), $site->getId()));
        $io->warning('Copy this token now, it will not be shown again:');
        $io->writeln($token);

        return Command::SUCCESS;
    }
}
