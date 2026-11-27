<?php

declare(strict_types=1);

namespace App\Command\Typo3;

use App\Command\AbstractSyncCommand;
use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Service\SecurityNotifier;
use App\Service\Typo3\PackagistVersionSynchronizer;
use App\Service\Typo3\SitePackageTerEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:typo3:sync-packagist-versions',
    description: 'Fetch the Packagist versions of the reported TYPO3 packages, then re-rate the reported extensions as outdated',
)]
#[AsCronTask('41 */6 * * *')] // every 6h, all packages per run; never at the full hour, as Packagist asks
class SyncPackagistVersionsCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly PackagistVersionSynchronizer $synchronizer,
        private readonly SitePackageTerEvaluator $evaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        EntityManagerInterface $entityManager,
        SecurityNotifier $securityNotifier,
    ) {
        parent::__construct($entityManager, $securityNotifier);
    }

    protected function syncAndEvaluate(): string
    {
        $packageCount = $this->synchronizer->sync();

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::Typo3CmsExtension);
        $this->evaluator->evaluateAll($packages);

        return sprintf('Synced the versions of %d TYPO3 packages via Packagist and re-evaluated %d reported extensions.', $packageCount, count($packages));
    }
}
