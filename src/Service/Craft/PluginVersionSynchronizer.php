<?php

declare(strict_types=1);

namespace App\Service\Craft;

use App\Entity\Craft\PluginVersion;
use App\Entity\PackageType;
use App\Entity\SitePackage;
use App\Repository\Craft\PluginVersionRepository;
use App\Repository\SitePackageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Syncs the Packagist versions of every plugin a Craft site reports with a Composer name.
 * Packagist serves the p2 files from a CDN, so all plugins are synced in one run.
 */
class PluginVersionSynchronizer
{
    public function __construct(
        private readonly PackagistApiClient $apiClient,
        private readonly PluginVersionRepository $repository,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int number of plugins synced; plugins unknown to Packagist are left out
     */
    public function sync(): int
    {
        $packageNames = array_unique(array_filter(
            array_map(static fn (SitePackage $package): ?string => $package->getComposerName(), $this->sitePackageRepository->findByPackageType(PackageType::CraftPlugin)),
            is_string(...),
        ));

        $synced = 0;
        foreach ($packageNames as $packageName) {
            $versions = $this->apiClient->versions($packageName);
            if (null === $versions) {
                continue; // e.g. a private plugin: its versions stay unknown
            }
            $this->store($packageName, $versions);
            ++$synced;
        }
        $this->entityManager->flush();

        return $synced;
    }

    /**
     * @param list<array{version: string, releaseDate: ?\DateTimeImmutable, craftConstraint: ?string}> $versions
     */
    private function store(string $packageName, array $versions): void
    {
        $known = [];
        foreach ($this->repository->findByPackageName($packageName) as $version) {
            $known[$version->getVersion()] = $version;
        }
        foreach ($versions as ['version' => $version, 'releaseDate' => $releaseDate, 'craftConstraint' => $constraint]) {
            if (isset($known[$version])) {
                $known[$version]->update($releaseDate, $constraint);
            } else {
                $this->entityManager->persist(new PluginVersion($packageName, $version, $releaseDate, $constraint));
            }
        }
    }
}
