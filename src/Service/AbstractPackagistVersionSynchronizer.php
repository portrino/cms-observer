<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AbstractPackagistVersion;
use App\Entity\PackageType;
use App\Entity\SitePackage;
use App\Repository\AbstractPackagistVersionRepository;
use App\Repository\SitePackageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Syncs the Packagist versions of every package of one type that sites report with a Composer
 * name. Packagist serves the p2 files from a CDN and the client revalidates them, so all
 * packages are synced in one run; each CMS reads what it needs from a version's "require".
 *
 * @template T of AbstractPackagistVersion
 */
abstract class AbstractPackagistVersionSynchronizer
{
    /**
     * @param AbstractPackagistVersionRepository<T> $versionRepository
     */
    public function __construct(
        private readonly PackagistApiClient $apiClient,
        private readonly AbstractPackagistVersionRepository $versionRepository,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int number of packages synced; packages unknown to Packagist are left out
     */
    public function sync(): int
    {
        $packageNames = array_unique(array_filter(
            array_map($this->composerName(...), $this->sitePackageRepository->findByPackageType($this->packageType())),
            is_string(...),
        ));

        $synced = 0;
        foreach ($packageNames as $packageName) {
            $versions = $this->apiClient->versions($packageName);
            if (null === $versions) {
                continue; // e.g. a private package: its versions stay unknown
            }
            $this->store($packageName, $versions);
            ++$synced;
        }
        $this->entityManager->flush();

        return $synced;
    }

    abstract protected function packageType(): PackageType;

    /** The name to look a reported package up by on Packagist */
    protected function composerName(SitePackage $package): ?string
    {
        return $package->getComposerName();
    }

    /**
     * @param array{version: string, releaseDate: ?\DateTimeImmutable, require: array<string, string>, sourceUrl: ?string, sourceReference: ?string} $data
     *
     * @return T
     */
    abstract protected function create(string $packageName, array $data): AbstractPackagistVersion;

    /**
     * @param T                                                                                                                                      $known
     * @param array{version: string, releaseDate: ?\DateTimeImmutable, require: array<string, string>, sourceUrl: ?string, sourceReference: ?string} $data
     */
    abstract protected function update(AbstractPackagistVersion $known, array $data): void;

    /**
     * @param list<array{version: string, releaseDate: ?\DateTimeImmutable, require: array<string, string>, sourceUrl: ?string, sourceReference: ?string}> $versions
     */
    private function store(string $packageName, array $versions): void
    {
        $known = [];
        foreach ($this->versionRepository->findByPackageName($packageName) as $version) {
            $known[$version->getVersion()] = $version;
        }
        foreach ($versions as $data) {
            if (isset($known[$data['version']])) {
                $this->update($known[$data['version']], $data);
            } else {
                $this->entityManager->persist($this->create($packageName, $data));
            }
        }
    }
}
