<?php

declare(strict_types=1);

namespace App\Command\Typo3;

use App\Command\AbstractSyncCommand;
use App\Service\SecurityNotifier;
use App\Service\Typo3\BreakingCommitSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:typo3:sync-breaking-commits',
    description: 'Read the commits of the TYPO3 packages\' Packagist versions on GitHub (with GITHUB_API_TOKEN), GitLab and Codeberg for breaking changes',
)]
#[AsCronTask('53 * * * *')] // hourly, a batch per run; each version is checked once
class SyncBreakingCommitsCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly BreakingCommitSynchronizer $synchronizer,
        EntityManagerInterface $entityManager,
        SecurityNotifier $securityNotifier,
    ) {
        parent::__construct($entityManager, $securityNotifier);
    }

    protected function syncAndEvaluate(): string
    {
        return sprintf('Read the commits of %d TYPO3 package versions on their VCS platforms.', $this->synchronizer->sync());
    }
}
