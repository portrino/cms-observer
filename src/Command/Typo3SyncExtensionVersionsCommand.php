<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Service\SecurityNotifier;
use App\Service\Typo3\ExtensionVersionSynchronizer;
use App\Service\Typo3\SitePackageTerEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:typo3:sync-extension-versions',
    description: 'Fetch the TER versions of a batch of reported extensions per run, then re-rate the reported extensions as insecure or outdated',
)]
#[AsCronTask('*/15 * * * *')] // every 15m - only MAX_KEYS_PER_RUN keys per run, so it needs frequent ticks to cover every extension
class Typo3SyncExtensionVersionsCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly ExtensionVersionSynchronizer $synchronizer,
        private readonly SitePackageTerEvaluator $terEvaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        EntityManagerInterface $entityManager,
        SecurityNotifier $securityNotifier,
    ) {
        parent::__construct($entityManager, $securityNotifier);
    }

    protected function syncAndEvaluate(): string
    {
        $syncedKeys = $this->synchronizer->sync();

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::Typo3CmsExtension);
        $this->terEvaluator->evaluateAll($packages);

        return sprintf(
            'Synced version history for %d extension key(s) via TER and re-evaluated %d reported extensions.',
            $syncedKeys,
            count($packages),
        );
    }
}
