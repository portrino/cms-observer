<?php

declare(strict_types=1);

namespace App\Command\Typo3;

use App\Command\AbstractSyncCommand;
use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Service\SecurityNotifier;
use App\Service\Typo3\ExtensionAdvisorySynchronizer;
use App\Service\Typo3\SitePackageAdvisoryEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:typo3:sync-extension-advisories',
    description: 'Fetch the TYPO3 security advisories for extensions, then re-rate every reported extension version',
)]
#[AsCronTask('11 * * * *')] // hourly - this is the security-advisory feed, worth checking often
class SyncExtensionAdvisoriesCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly ExtensionAdvisorySynchronizer $synchronizer,
        private readonly SitePackageAdvisoryEvaluator $advisoryEvaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        EntityManagerInterface $entityManager,
        SecurityNotifier $securityNotifier,
    ) {
        parent::__construct($entityManager, $securityNotifier);
    }

    protected function syncAndEvaluate(): string
    {
        $newAdvisories = $this->synchronizer->sync();

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::Typo3CmsExtension);
        $this->advisoryEvaluator->evaluateAll($packages);

        return sprintf(
            'Synced %d new advisories and re-evaluated %d reported extensions.',
            $newAdvisories,
            count($packages),
        );
    }
}
