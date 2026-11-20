<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SiteEventType;
use App\Repository\SiteRepository;
use App\Service\SecurityNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:check-stale-sites',
    description: 'Notify about sites that stopped sending reports',
)]
#[AsCronTask('7 * * * *')] // hourly - a site is notified once per outage, so a frequent check only shortens the delay
class CheckStaleSitesCommand extends Command
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityNotifier $securityNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sites = $this->siteRepository->findStaleNotYetNotified();

        foreach ($sites as $site) {
            $this->securityNotifier->addFinding(
                $site,
                'No reports',
                sprintf('No report since %s, the security status is no longer up to date.', $site->getLastSeenAt()?->format('Y-m-d H:i')),
                SiteEventType::StoppedReporting,
            );
        }

        $this->entityManager->flush();
        $this->securityNotifier->flush();

        new SymfonyStyle($input, $output)->success(sprintf('%d site(s) newly stopped reporting.', count($sites)));

        return Command::SUCCESS;
    }
}
