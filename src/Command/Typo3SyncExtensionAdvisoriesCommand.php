<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Service\SecurityNotifier;
use App\Service\SitePackageAdvisoryEvaluator;
use App\Service\Typo3ExtensionAdvisorySynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:typo3:sync-extension-advisories',
    description: 'Fetch TYPO3 extension security advisories and cross-reference them against reported extension versions',
)]
#[AsCronTask('11 * * * *')] // hourly - this is the security-advisory feed, worth checking often
class Typo3SyncExtensionAdvisoriesCommand extends Command
{
    public function __construct(
        private readonly Typo3ExtensionAdvisorySynchronizer $synchronizer,
        private readonly SitePackageAdvisoryEvaluator $advisoryEvaluator,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityNotifier $securityNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $newAdvisories = $this->synchronizer->sync();

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::Typo3CmsExtension);
        $this->advisoryEvaluator->evaluateAll($packages);
        $this->entityManager->flush();
        $this->securityNotifier->flush();

        $io->success(sprintf(
            'Synced %d new advisories and re-evaluated %d reported extensions.',
            $newAdvisories,
            count($packages),
        ));

        return Command::SUCCESS;
    }
}
