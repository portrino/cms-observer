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
use Symfony\Component\Translation\TranslatableMessage;

#[AsCommand(
    name: 'app:check-stale-sites',
    description: 'Mail about projects that sent no report for 2 days, once per outage',
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
                new TranslatableMessage('event.subject.no_reports'),
                new TranslatableMessage('event.detail.stale', ['since' => $site->getLastSeenAt()?->getTimestamp() ?? 0]),
                SiteEventType::StoppedReporting,
            );
        }

        $this->entityManager->flush();
        $this->securityNotifier->flush();

        new SymfonyStyle($input, $output)->success(sprintf('%d site(s) newly stopped reporting.', count($sites)));

        return Command::SUCCESS;
    }
}
