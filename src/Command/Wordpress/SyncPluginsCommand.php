<?php

declare(strict_types=1);

namespace App\Command\Wordpress;

use App\Command\AbstractSyncCommand;
use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Service\SecurityNotifier;
use App\Service\Wordpress\PluginSynchronizer;
use App\Service\Wordpress\SitePackageEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:wordpress:sync-plugins',
    description: 'Fetch the WordPress.org versions of a batch of reported plugins per run, then re-rate the reported plugins as outdated',
)]
#[AsCronTask('*/15 * * * *')] // every 15m - only MAX_SLUGS_PER_RUN slugs per run, so it needs frequent ticks to cover every plugin
class SyncPluginsCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly PluginSynchronizer $synchronizer,
        private readonly SitePackageEvaluator $wordpressEvaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        EntityManagerInterface $entityManager,
        SecurityNotifier $securityNotifier,
    ) {
        parent::__construct($entityManager, $securityNotifier);
    }

    protected function syncAndEvaluate(): string
    {
        $syncedSlugs = $this->synchronizer->sync();

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::WordpressPlugin);
        $this->wordpressEvaluator->evaluateAll($packages);

        return sprintf(
            'Synced info for %d plugin slug(s) via WordPress.org and re-evaluated %d reported plugins.',
            $syncedSlugs,
            count($packages),
        );
    }
}
