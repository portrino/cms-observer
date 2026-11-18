<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SiteRepository;
use App\Service\EndOfLifeReleaseSynchronizer;
use App\Service\SecurityNotifier;
use App\Service\SiteRuntimeVersionEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:sync-endoflife-releases',
    description: 'Fetch PHP/MariaDB/MySQL release-cycle EOL data from endoflife.date and cross-reference reported site versions',
)]
#[AsCronTask('17 3 * * *')] // once a day - EOL cycles change on the order of months, not hours
class SyncEndOfLifeReleasesCommand extends Command
{
    public function __construct(
        private readonly EndOfLifeReleaseSynchronizer $synchronizer,
        private readonly SiteRuntimeVersionEvaluator $runtimeVersionEvaluator,
        private readonly SiteRepository $siteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityNotifier $securityNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cycleCount = $this->synchronizer->sync();

        $sites = $this->siteRepository->findAll();
        $this->runtimeVersionEvaluator->evaluateAll($sites);
        $this->entityManager->flush();
        $this->securityNotifier->flush();

        $io->success(sprintf(
            'Synced %d release cycles from endoflife.date and re-evaluated %d sites.',
            $cycleCount,
            count($sites),
        ));

        return Command::SUCCESS;
    }
}
