<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Entity\AbstractPackagistVersion;
use App\Entity\PackageType;
use App\Entity\SitePackage;
use App\Entity\Typo3\PackagistVersion;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3\CoreReleaseRepository;
use App\Repository\Typo3\PackagistVersionRepository;
use App\Service\AbstractPackagistVersionSynchronizer;
use App\Service\PackagistApiClient;
use App\Utility\Typo3\ExtensionUtility;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The Packagist versions of the reported TYPO3 packages, with the known TYPO3 majors they
 * install on.
 *
 * @extends AbstractPackagistVersionSynchronizer<PackagistVersion>
 */
class PackagistVersionSynchronizer extends AbstractPackagistVersionSynchronizer
{
    /** @var list<int>|null */
    private ?array $majors = null;

    // the concrete repository, for autowiring
    public function __construct(
        PackagistApiClient $apiClient,
        PackagistVersionRepository $versionRepository,
        SitePackageRepository $sitePackageRepository,
        EntityManagerInterface $entityManager,
        private readonly CoreReleaseRepository $coreReleaseRepository,
        private readonly ExtensionVersionCatalog $versionCatalog,
    ) {
        parent::__construct($apiClient, $versionRepository, $sitePackageRepository, $entityManager);
    }

    public function sync(): int
    {
        // a major released since the last run counts from this one on
        $this->majors = $this->coreReleaseRepository->findMajors();

        return parent::sync();
    }

    protected function packageType(): PackageType
    {
        return PackageType::Typo3CmsExtension;
    }

    // older clients report no Composer name, the TER's Packagist link has it
    protected function composerName(SitePackage $package): ?string
    {
        return $this->versionCatalog->composerNameOf($package->getName(), $package->getComposerName());
    }

    protected function create(string $packageName, array $data): PackagistVersion
    {
        return new PackagistVersion($packageName, $data['version'], $data['releaseDate'], ExtensionUtility::supportedCoreMajors($data['require'], $this->majors ?? []));
    }

    protected function update(AbstractPackagistVersion $known, array $data): void
    {
        $known->update($data['releaseDate'], ExtensionUtility::supportedCoreMajors($data['require'], $this->majors ?? []));
    }
}
