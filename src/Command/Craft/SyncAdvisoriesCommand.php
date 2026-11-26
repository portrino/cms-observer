<?php

declare(strict_types=1);

namespace App\Command\Craft;

use App\Command\AbstractSyncCommand;
use App\Entity\CmsType;
use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Repository\SiteRepository;
use App\Service\Craft\AdvisorySynchronizer;
use App\Service\Craft\SitePackageEvaluator;
use App\Service\SecurityNotifier;
use App\Service\SiteCoreVersionEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:craft:sync-advisories',
    description: 'Fetch the Packagist security advisories of Craft CMS and the reported plugins, then re-rate every Craft CMS project',
)]
#[AsCronTask('47 * * * *')] // hourly, like the TYPO3 advisories: a new advisory should alert the same day
class SyncAdvisoriesCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly AdvisorySynchronizer $synchronizer,
        private readonly SiteCoreVersionEvaluator $coreVersionEvaluator,
        private readonly SiteRepository $siteRepository,
        private readonly SitePackageEvaluator $packageEvaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        EntityManagerInterface $entityManager,
        SecurityNotifier $securityNotifier,
    ) {
        parent::__construct($entityManager, $securityNotifier);
    }

    protected function syncAndEvaluate(): string
    {
        $advisoryCount = $this->synchronizer->sync();

        $sites = $this->siteRepository->findBy(['type' => CmsType::Craft]);
        $this->coreVersionEvaluator->evaluateAll($sites);
        $packages = $this->sitePackageRepository->findByPackageType(PackageType::CraftPlugin);
        $this->packageEvaluator->evaluateAll($packages);

        return sprintf('Synced %d Craft CMS advisories and re-evaluated %d sites and %d plugins.', $advisoryCount, count($sites), count($packages));
    }
}
