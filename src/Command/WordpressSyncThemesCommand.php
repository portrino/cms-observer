<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Service\SecurityNotifier;
use App\Service\SitePackageWordpressEvaluator;
use App\Service\WordpressThemeSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:wordpress:sync-themes',
    description: 'Fetch the latest WordPress.org version of every reported theme, then re-rate the reported themes as outdated',
)]
#[AsCronTask('53 */6 * * *')] // every 6h - like the core releases; all themes in one run
class WordpressSyncThemesCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly WordpressThemeSynchronizer $synchronizer,
        private readonly SitePackageWordpressEvaluator $wordpressEvaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        EntityManagerInterface $entityManager,
        SecurityNotifier $securityNotifier,
    ) {
        parent::__construct($entityManager, $securityNotifier);
    }

    protected function syncAndEvaluate(): string
    {
        $syncedSlugs = $this->synchronizer->sync();

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::WordpressTheme);
        $this->wordpressEvaluator->evaluateAll($packages);

        return sprintf(
            'Synced %d theme slug(s) via WordPress.org and re-evaluated %d reported themes.',
            $syncedSlugs,
            count($packages),
        );
    }
}
