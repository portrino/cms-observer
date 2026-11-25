<?php

declare(strict_types=1);

namespace App\Service\Wordpress;

use App\Entity\PackageType;
use App\Entity\SitePackage;
use App\Entity\WordpressThemeInfo;
use App\Repository\SitePackageRepository;
use App\Repository\WordpressThemeInfoRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Syncs the latest version of every reported theme from WordPress.org. One request per theme
 * slug; sites have few themes, so unlike the plugins all of them are synced in every run.
 */
class ThemeSynchronizer
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly WordpressThemeInfoRepository $themeInfoRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int number of theme slugs WordPress.org knows
     */
    public function sync(): int
    {
        $slugs = array_unique(array_map(
            static fn (SitePackage $package): string => $package->getName(),
            $this->sitePackageRepository->findByPackageType(PackageType::WordpressTheme),
        ));

        $synced = 0;
        foreach ($slugs as $slug) {
            $info = $this->apiClient->getThemeInfo($slug);
            if (null === $info) {
                continue; // e.g. a premium theme: its "outdated" stays unknown
            }

            $existing = $this->themeInfoRepository->findOneBySlug($slug);
            if (null !== $existing) {
                $existing->update($info['version'], $info['requires']);
            } else {
                $this->entityManager->persist(new WordpressThemeInfo($slug, $info['version'], $info['requires']));
            }
            ++$synced;
        }

        $this->entityManager->flush();

        return $synced;
    }
}
