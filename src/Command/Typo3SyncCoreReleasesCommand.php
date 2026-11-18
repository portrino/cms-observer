<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CmsType;
use App\Repository\SiteRepository;
use App\Service\SecurityNotifier;
use App\Service\SiteCoreVersionEvaluator;
use App\Service\Typo3CoreReleaseSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:typo3:sync-core-releases',
    description: 'Fetch TYPO3 core release data from get.typo3.org and store it for security cross-referencing',
)]
#[AsCronTask('23 */6 * * *')] // every 6h - catches a new security release the same day it ships
class Typo3SyncCoreReleasesCommand extends Command
{
    public function __construct(
        private readonly Typo3CoreReleaseSynchronizer $synchronizer,
        private readonly SiteCoreVersionEvaluator $coreVersionEvaluator,
        private readonly SiteRepository $siteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityNotifier $securityNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $releaseCount = $this->synchronizer->sync();

        $sites = $this->siteRepository->findBy(['type' => CmsType::Typo3]);
        $this->coreVersionEvaluator->evaluateAll($sites);
        $this->entityManager->flush();
        $this->securityNotifier->flush();

        $io->success(sprintf(
            'Synced %d TYPO3 core releases and re-evaluated %d sites.',
            $releaseCount,
            count($sites),
        ));

        return Command::SUCCESS;
    }
}
