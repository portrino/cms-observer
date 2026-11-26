<?php

declare(strict_types=1);

namespace App\Command\Craft;

use App\Command\AbstractSyncCommand;
use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Service\Craft\PluginVersionSynchronizer;
use App\Service\Craft\SitePackageEvaluator;
use App\Service\SecurityNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:craft:sync-plugin-versions',
    description: 'Fetch the Packagist versions of the reported Craft plugins, then re-rate them as outdated',
)]
#[AsCronTask('13 */6 * * *')] // every 6h, all plugins per run; never at the full hour, as Packagist asks
class SyncPluginVersionsCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly PluginVersionSynchronizer $synchronizer,
        private readonly SitePackageEvaluator $evaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        EntityManagerInterface $entityManager,
        SecurityNotifier $securityNotifier,
    ) {
        parent::__construct($entityManager, $securityNotifier);
    }

    protected function syncAndEvaluate(): string
    {
        $pluginCount = $this->synchronizer->sync();

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::CraftPlugin);
        $this->evaluator->evaluateAll($packages);

        return sprintf('Synced the versions of %d Craft plugins via Packagist and re-evaluated %d reported plugins.', $pluginCount, count($packages));
    }
}
