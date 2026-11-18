<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Service\SecurityNotifier;
use App\Service\SitePackageWordpressEvaluator;
use App\Service\WordpressPluginSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:wordpress:sync-plugins',
    description: 'Resolve plugin outdatedness for reported WordPress plugins via the WordPress.org plugin API',
)]
#[AsCronTask('*/15 * * * *')] // every 15m - only MAX_SLUGS_PER_RUN slugs per run, so it needs frequent ticks to cover every plugin
class WordpressSyncPluginsCommand extends Command
{
    public function __construct(
        private readonly WordpressPluginSynchronizer $synchronizer,
        private readonly SitePackageWordpressEvaluator $wordpressEvaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityNotifier $securityNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $syncedSlugs = $this->synchronizer->sync();

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::WordpressPlugin);
        $this->wordpressEvaluator->evaluateAll($packages);
        $this->entityManager->flush();
        $this->securityNotifier->flush();

        $io->success(sprintf(
            'Synced info for %d plugin slug(s) via WordPress.org and re-evaluated %d reported extensions.',
            $syncedSlugs,
            count($packages),
        ));

        return Command::SUCCESS;
    }
}
