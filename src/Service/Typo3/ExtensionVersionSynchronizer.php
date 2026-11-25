<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Entity\PackageType;
use App\Entity\Typo3\TerExtensionMeta;
use App\Entity\Typo3\TerExtensionVersion;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3\TerExtensionMetaRepository;
use App\Repository\Typo3\TerExtensionVersionRepository;
use App\Service\SiteCoreVersionEvaluator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Syncs full TER version history for every distinct extension key currently
 * reported by any TYPO3 site - one API request per key regardless of how many
 * versions it has (TerExtensionApiClient::getVersionHistory()), backing both
 * the insecure/outdated cross-reference (SitePackageTerEvaluator) and the
 * "newer versions" table on the site detail page. Also fetches that key's
 * Packagist/repository/manual links (getExtensionMeta()) - a second request
 * per key, still well within the rate limit at MAX_KEYS_PER_RUN below.
 *
 * Deliberately not run per-report like SiteCoreVersionEvaluator: this is a
 * live, rate-limited (100 requests/window) third-party call, not a local DB
 * read, so coupling it to ingestion would slow the endpoint down and risk
 * the rate limit under bursty traffic. Runs only via this periodic sync.
 */
class ExtensionVersionSynchronizer
{
    /** Upper bound on distinct extension keys synced per run, to stay well under TER's rate limit. */
    private const int MAX_KEYS_PER_RUN = 20;

    public function __construct(
        private readonly TerExtensionApiClient $apiClient,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly TerExtensionVersionRepository $versionRepository,
        private readonly TerExtensionMetaRepository $metaRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int number of extension keys synced
     */
    public function sync(): int
    {
        $keys = $this->keysToSync();
        foreach ($keys as $key) {
            $this->syncVersions($key);
            $this->syncMeta($key);
        }

        $this->entityManager->flush();

        return count($keys);
    }

    /**
     * @return list<string>
     */
    private function keysToSync(): array
    {
        $keys = [];
        foreach ($this->sitePackageRepository->findByPackageType(PackageType::Typo3CmsExtension) as $package) {
            $keys[$package->getName()] = true;
        }

        // Never-synced keys first, so a key beyond MAX_KEYS_PER_RUN isn't stuck
        // behind the same already-known keys every single run - only once
        // every key has at least one data point does this start refreshing
        // already-known ones for new TER releases, least recently fetched first.
        $alreadySynced = array_fill_keys($this->metaRepository->findAllExtensionKeys(), true);
        $orderedKeys = array_merge(array_diff_key($keys, $alreadySynced), array_intersect_key($alreadySynced, $keys));

        return array_slice(array_map(strval(...), array_keys($orderedKeys)), 0, self::MAX_KEYS_PER_RUN);
    }

    private function syncVersions(string $key): void
    {
        // TER's history for a given key can contain more than one entry for the
        // same version number (seen live for "powermail" and 1.6.7) - track what
        // this batch has already persisted, since a not-yet-flushed entity isn't
        // visible to a fresh findOneByExtensionKeyAndVersion() DB lookup.
        $seenInBatch = [];

        foreach ($this->apiClient->getVersionHistory($key) as $data) {
            if (isset($seenInBatch[$data['version']])) {
                continue;
            }
            $seenInBatch[$data['version']] = true;

            $existing = $this->versionRepository->findOneByExtensionKeyAndVersion($key, $data['version']);

            if (null !== $existing) {
                $existing->update(
                    title: $data['title'],
                    insecure: $data['insecure'],
                    description: $data['description'],
                    uploadComment: $data['uploadComment'],
                    state: $data['state'],
                    typo3Versions: $data['typo3Versions'],
                    uploadDate: $data['uploadDate'],
                );
            } else {
                $this->entityManager->persist(new TerExtensionVersion(
                    extensionKey: $key,
                    version: $data['version'],
                    title: $data['title'],
                    insecure: $data['insecure'],
                    description: $data['description'],
                    uploadComment: $data['uploadComment'],
                    state: $data['state'],
                    typo3Versions: $data['typo3Versions'],
                    uploadDate: $data['uploadDate'],
                ));
            }
        }
    }

    private function syncMeta(string $key): void
    {
        $meta = $this->apiClient->getExtensionMeta($key);
        if (null === $meta) {
            return;
        }

        $existingMeta = $this->metaRepository->findOneByExtensionKey($key);
        if (null !== $existingMeta) {
            $existingMeta->update($meta['packagistUrl'], $meta['repositoryUrl'], $meta['manualUrl']);

            return;
        }

        $this->entityManager->persist(new TerExtensionMeta(
            $key,
            $meta['packagistUrl'],
            $meta['repositoryUrl'],
            $meta['manualUrl'],
        ));
    }
}
