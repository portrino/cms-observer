<?php

declare(strict_types=1);

namespace App\Command\Wordpress;

use App\Command\AbstractSyncCommand;
use App\Entity\CmsType;
use App\Repository\SiteRepository;
use App\Service\SecurityNotifier;
use App\Service\SiteCoreVersionEvaluator;
use App\Service\Wordpress\CoreReleaseSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:wordpress:sync-core-releases',
    description: 'Fetch the WordPress core releases and which are insecure from api.wordpress.org, then re-rate the WordPress version of every project',
)]
#[AsCronTask('37 */6 * * *')] // every 6h - catches a new security release the same day it ships
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

        $sites = $this->siteRepository->findBy(['type' => CmsType::WordPress]);
        $this->coreVersionEvaluator->evaluateAll($sites);

        return sprintf(
            'Synced %d WordPress core releases and re-evaluated %d sites.',
            $releaseCount,
            count($sites),
        );
    }
}
