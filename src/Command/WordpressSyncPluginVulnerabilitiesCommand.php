<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PackageType;
use App\Repository\SitePackageRepository;
use App\Service\SecurityNotifier;
use App\Service\SitePackageWordpressEvaluator;
use App\Service\WordfenceApiClient;
use App\Service\WordpressPluginVulnerabilitySynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:wordpress:sync-plugin-vulnerabilities',
    description: 'Fetch the Wordfence Intelligence vulnerability feed (needs WORDFENCE_API_KEY), then re-rate every reported plugin version',
)]
#[AsCronTask('43 4 * * *')] // once a day - the feed is complete (~160 MB) and Wordfence rate-limits repeated downloads
class WordpressSyncPluginVulnerabilitiesCommand extends Command
{
    public function __construct(
        private readonly WordfenceApiClient $apiClient,
        private readonly WordpressPluginVulnerabilitySynchronizer $synchronizer,
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

        // not an error: the scheduler runs this everywhere, also where no key is configured
        if (!$this->apiClient->isConfigured()) {
            $io->warning('WORDFENCE_API_KEY is not set, skipping the WordPress plugin vulnerability sync.');

            return Command::SUCCESS;
        }

        try {
            $stats = $this->synchronizer->sync();
        } catch (ClientException $e) {
            if (429 !== $e->getResponse()->getStatusCode()) {
                throw $e;
            }

            // a failure, so the status page counts the missed run: overdue after three in a row
            $io->warning('Wordfence rate limit hit (HTTP 429), keeping the last synced data until the next run.');

            return Command::FAILURE;
        }

        if ($stats['deletionSkipped']) {
            $io->warning('The Wordfence feed had less than half of the stored records, so none were removed. Check the feed if this repeats.');
        }

        $packages = $this->sitePackageRepository->findByPackageType(PackageType::WordpressPlugin);
        $this->wordpressEvaluator->evaluateAll($packages);
        $this->entityManager->flush();
        $this->securityNotifier->flush();

        $io->success(sprintf(
            'Synced Wordfence vulnerabilities (%d new, %d updated, %d removed) and re-evaluated %d reported plugins.',
            $stats['inserted'],
            $stats['updated'],
            $stats['deleted'],
            count($packages),
        ));

        return Command::SUCCESS;
    }
}
