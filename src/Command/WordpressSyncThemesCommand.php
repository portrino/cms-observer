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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:wordpress:sync-themes',
    description: 'Fetch the latest WordPress.org version of every reported theme, then re-rate the reported themes as outdated',
)]
#[AsCronTask('53 */6 * * *')] // every 6h - like the core releases; all themes in one run
class WordpressSyncThemesCommand extends Command
{
    public function __construct(
        private readonly WordpressThemeSynchronizer $synchronizer,
        private readonly SitePackageWordpressEvaluator $wordpressEvaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityNotifier $securityNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $syncedSlugs = $this->synchronizer->sync();

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::WordpressTheme);
        $this->wordpressEvaluator->evaluateAll($packages);
        $this->entityManager->flush();
        $this->securityNotifier->flush();

        new SymfonyStyle($input, $output)->success(sprintf(
            'Synced %d theme slug(s) via WordPress.org and re-evaluated %d reported themes.',
            $syncedSlugs,
            count($packages),
        ));

        return Command::SUCCESS;
    }
}
