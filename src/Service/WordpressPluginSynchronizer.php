<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PackageType;
use App\Entity\WordpressPluginInfo;
use App\Entity\WordpressPluginVersion;
use App\Repository\SitePackageRepository;
use App\Repository\WordpressPluginInfoRepository;
use App\Repository\WordpressPluginVersionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Syncs WordPress.org plugin info for every distinct plugin slug currently
 * reported by a WordPress site - one API request per slug
 * (WordpressApiClient::getPluginInfo()), mirroring
 * Typo3ExtensionVersionSynchronizer's per-key sync loop.
 *
 * Unlike TER, WordPress.org's plugin info API only exposes the latest
 * published version's metadata, so per-version release dates and
 * compatibility (WordpressPluginVersion) are instead scraped from the
 * plugin's SVN mirror: one PROPFIND per slug for every tag's date, then one
 * request per newer version to read its bootstrap file's "Requires at
 * least" header. Only versions newer than the oldest version any monitored
 * site currently has installed are fetched (capped further by
 * MAX_VERSIONS_PER_SLUG_PER_RUN) - older tags are never shown to users, so
 * there's no reason to spend requests scraping them.
 */
class WordpressPluginSynchronizer
{
    /** Upper bound on distinct plugin slugs synced per run. */
    private const int MAX_SLUGS_PER_RUN = 40;

    /** Upper bound on newer versions scraped per slug per run. */
    private const int MAX_VERSIONS_PER_SLUG_PER_RUN = 15;

    public function __construct(
        private readonly WordpressApiClient $apiClient,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly WordpressPluginInfoRepository $pluginInfoRepository,
        private readonly WordpressPluginVersionRepository $versionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int number of plugin slugs synced
     */
    public function sync(): int
    {
        $installedVersionsBySlug = [];
        foreach ($this->sitePackageRepository->findByPackageType(PackageType::WordpressPlugin) as $package) {
            $installedVersionsBySlug[$package->getName()][] = $package->getVersion();
        }

        $synced = 0;
        foreach (array_slice(array_keys($installedVersionsBySlug), 0, self::MAX_SLUGS_PER_RUN) as $slug) {
            $info = $this->apiClient->getPluginInfo($slug);
            if (null === $info) {
                continue;
            }

            $existingInfo = $this->pluginInfoRepository->findOneBySlug($slug);
            $bootstrapFilename = $existingInfo?->getBootstrapFilename()
                ?? $this->apiClient->findBootstrapFilename($slug, $info['version']);

            if (null !== $existingInfo) {
                $existingInfo->update($info['version'], $info['requires'], $info['tested'], $info['description'], $bootstrapFilename);
            } else {
                $this->entityManager->persist(new WordpressPluginInfo(
                    $slug,
                    $info['version'],
                    $info['requires'],
                    $info['tested'],
                    $info['description'],
                    $bootstrapFilename,
                ));
            }

            $this->syncVersions($slug, $installedVersionsBySlug[$slug], $bootstrapFilename);

            ++$synced;
        }

        $this->entityManager->flush();

        return $synced;
    }

    /**
     * Release date and "requires at least" are immutable historical facts
     * once a version is published, so already-cached versions are skipped
     * entirely rather than re-fetched - only genuinely new-to-us versions
     * count against MAX_VERSIONS_PER_SLUG_PER_RUN. That also means a plugin
     * whose installed version is many releases behind gets backfilled
     * gradually over successive sync runs instead of never progressing past
     * the same oldest 15 versions every time.
     *
     * @param string[] $installedVersions
     */
    private function syncVersions(string $slug, array $installedVersions, ?string $bootstrapFilename): void
    {
        usort($installedVersions, static fn (string $a, string $b): int => version_compare($a, $b));
        $minInstalled = $installedVersions[0] ?? null;
        if (null === $minInstalled) {
            return;
        }

        $tagDates = $this->apiClient->getTagDates($slug);

        $newerVersions = array_filter(
            array_keys($tagDates),
            static fn (string $version): bool => version_compare($version, $minInstalled, '>'),
        );
        usort($newerVersions, static fn (string $a, string $b): int => version_compare($a, $b));

        $fetched = 0;
        foreach ($newerVersions as $version) {
            if (null !== $this->versionRepository->findOneBySlugAndVersion($slug, $version)) {
                continue;
            }

            if ($fetched >= self::MAX_VERSIONS_PER_SLUG_PER_RUN) {
                break;
            }

            $requiresAtLeast = null !== $bootstrapFilename
                ? $this->apiClient->getRequiresAtLeast($slug, $bootstrapFilename, $version)
                : null;

            $this->entityManager->persist(new WordpressPluginVersion($slug, $version, $tagDates[$version], $requiresAtLeast));
            ++$fetched;
        }
    }
}
