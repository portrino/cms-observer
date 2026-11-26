<?php

declare(strict_types=1);

namespace App\Command\Craft;

use App\Command\AbstractSyncCommand;
use App\Entity\CmsType;
use App\Repository\SiteRepository;
use App\Service\Craft\CoreReleaseSynchronizer;
use App\Service\SecurityNotifier;
use App\Service\SiteCoreVersionEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:craft:sync-core-releases',
    description: 'Fetch the Craft CMS releases from Packagist, then re-rate the Craft CMS version of every project',
)]
#[AsCronTask('29 */6 * * *')] // every 6h, like the other CMS; never at the full hour, as Packagist asks
class SyncCoreReleasesCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly CoreReleaseSynchronizer $synchronizer,
        private readonly SiteCoreVersionEvaluator $coreVersionEvaluator,
        private readonly SiteRepository $siteRepository,
        EntityManagerInterface $entityManager,
        SecurityNotifier $securityNotifier,
    ) {
        parent::__construct($entityManager, $securityNotifier);
    }

    protected function syncAndEvaluate(): string
    {
        $releaseCount = $this->synchronizer->sync();

        $sites = $this->siteRepository->findBy(['type' => CmsType::Craft]);
        $this->coreVersionEvaluator->evaluateAll($sites);

        return sprintf('Synced %d Craft CMS releases and re-evaluated %d sites.', $releaseCount, count($sites));
    }
}
