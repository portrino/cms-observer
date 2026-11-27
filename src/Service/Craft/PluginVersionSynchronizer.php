<?php

declare(strict_types=1);

namespace App\Service\Craft;

use App\Entity\AbstractPackagistVersion;
use App\Entity\Craft\PluginVersion;
use App\Entity\PackageType;
use App\Repository\Craft\PluginVersionRepository;
use App\Repository\SitePackageRepository;
use App\Service\AbstractPackagistVersionSynchronizer;
use App\Service\PackagistApiClient;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The Packagist versions of the reported Craft plugins, with the Craft CMS versions they
 * require (craftcms/cms).
 *
 * @extends AbstractPackagistVersionSynchronizer<PluginVersion>
 */
class PluginVersionSynchronizer extends AbstractPackagistVersionSynchronizer
{
    // the concrete repository, for autowiring
    public function __construct(PackagistApiClient $apiClient, PluginVersionRepository $versionRepository, SitePackageRepository $sitePackageRepository, EntityManagerInterface $entityManager)
    {
        parent::__construct($apiClient, $versionRepository, $sitePackageRepository, $entityManager);
    }

    protected function packageType(): PackageType
    {
        return PackageType::CraftPlugin;
    }

    protected function create(string $packageName, array $data): PluginVersion
    {
        return new PluginVersion($packageName, $data['version'], $data['releaseDate'], $data['require']['craftcms/cms'] ?? null);
    }

    protected function update(AbstractPackagistVersion $known, array $data): void
    {
        $known->update($data['releaseDate'], $data['require']['craftcms/cms'] ?? null);
    }
}
