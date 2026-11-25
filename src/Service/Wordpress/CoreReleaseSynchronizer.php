<?php

declare(strict_types=1);

namespace App\Service\Wordpress;

use App\Entity\WordpressCoreRelease;
use App\Repository\WordpressCoreReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Syncs WordPress core release data from api.wordpress.org's stable-check
 * feed - a single global feed shared by every monitored WordPress site, so
 * it's synced once here rather than by each site. Unlike TYPO3's release
 * feed, this one already gives us insecure/outdated/latest directly per
 * version, so there's no separate maintenance-window logic to replicate.
 * Release dates aren't in that feed either, so they're scraped separately
 * from core.svn.wordpress.org (Wordpress\ApiClient::getCoreTagDates()) - one
 * extra request per sync run, not per version.
 */
class CoreReleaseSynchronizer
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly WordpressCoreReleaseRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function sync(): int
    {
        $statuses = $this->apiClient->getCoreReleaseStatuses();

        $latestVersion = array_search('latest', $statuses, true);
        if (false === $latestVersion) {
            // Defensive only - the feed should always contain exactly one "latest" entry.
            return 0;
        }

        $releaseDates = $this->apiClient->getCoreTagDates();

        foreach ($statuses as $version => $status) {
            $insecure = 'insecure' === $status;
            $releaseDate = $releaseDates[$version] ?? null;

            $existing = $this->repository->findOneByVersion($version);
            if (null !== $existing) {
                $existing->update($latestVersion, $insecure, $releaseDate);
            } else {
                $this->entityManager->persist(new WordpressCoreRelease($version, $latestVersion, $insecure, $releaseDate));
            }
        }

        $this->entityManager->flush();

        return count($statuses);
    }
}
