<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SiteRepository;
use App\Service\EndOfLifeReleaseSynchronizer;
use App\Service\SecurityNotifier;
use App\Service\SiteRuntimeVersionEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:sync-endoflife-releases',
    description: 'Fetch the support cycles of PHP, MariaDB, MySQL, PostgreSQL, TYPO3 and Craft CMS from endoflife.date, then re-rate the PHP and database version of every project',
)]
#[AsCronTask('17 3 * * *')] // once a day - EOL cycles change on the order of months, not hours
class SyncEndOfLifeReleasesCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly EndOfLifeReleaseSynchronizer $synchronizer,
        private readonly SiteRuntimeVersionEvaluator $runtimeVersionEvaluator,
        private readonly SiteRepository $siteRepository,
        EntityManagerInterface $entityManager,
        SecurityNotifier $securityNotifier,
    ) {
        parent::__construct($entityManager, $securityNotifier);
    }

    protected function syncAndEvaluate(): string
    {
        $cycleCount = $this->synchronizer->sync();

        $sites = $this->siteRepository->findAll();
        $this->runtimeVersionEvaluator->evaluateAll($sites);

        return sprintf(
            'Synced %d release cycles from endoflife.date and re-evaluated %d sites.',
            $cycleCount,
            count($sites),
        );
    }
}
