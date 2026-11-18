<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Service\SecurityNotifier;
use App\Service\SitePackageTerEvaluator;
use App\Service\Typo3ExtensionVersionSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:typo3:sync-extension-versions',
    description: 'Resolve extension insecurity/outdatedness for reported extensions via the TER API',
)]
#[AsCronTask('*/15 * * * *')] // every 15m - only MAX_KEYS_PER_RUN keys per run, so it needs frequent ticks to cover every extension
class Typo3SyncExtensionVersionsCommand extends Command
{
    public function __construct(
        private readonly Typo3ExtensionVersionSynchronizer $synchronizer,
        private readonly SitePackageTerEvaluator $terEvaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityNotifier $securityNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncedKeys = $this->synchronizer->sync();

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::Typo3CmsExtension);
        $this->terEvaluator->evaluateAll($packages);
        $this->entityManager->flush();
        $this->securityNotifier->flush();

        $io->success(sprintf(
            'Synced version history for %d extension key(s) via TER and re-evaluated %d reported extensions.',
            $syncedKeys,
            count($packages),
        ));

        return Command::SUCCESS;
    }
}
