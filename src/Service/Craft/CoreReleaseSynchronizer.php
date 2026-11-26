<?php

declare(strict_types=1);

namespace App\Service\Craft;

use App\Entity\Craft\CoreRelease;
use App\Repository\Craft\AdvisoryRepository;
use App\Repository\Craft\CoreReleaseRepository;
use App\Repository\EndOfLifeReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Syncs the stable Craft CMS releases (craftcms/cms on Packagist). A release is insecure when
 * its major is past end of life (endoflife.date, product craft-cms) or a Packagist advisory
 * affects it; the latest version is the newest of its own major, as Craft supports several.
 */
class CoreReleaseSynchronizer
{
    public function __construct(
        private readonly PackagistApiClient $apiClient,
        private readonly CoreReleaseRepository $repository,
        private readonly AdvisoryRepository $advisoryRepository,
        private readonly EndOfLifeReleaseRepository $endOfLifeReleaseRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int number of releases synced
     */
    public function sync(): int
    {
        $versions = $this->apiClient->versions(AdvisorySynchronizer::CORE_PACKAGE) ?? [];

        $latestByMajor = [];
        foreach ($versions as ['version' => $version]) {
            $major = self::majorOf($version);
            if (!isset($latestByMajor[$major]) || version_compare($version, $latestByMajor[$major], '>')) {
                $latestByMajor[$major] = $version;
            }
        }

        $known = [];
        foreach ($this->repository->findAll() as $release) {
            $known[$release->getVersion()] = $release;
        }
        foreach ($versions as ['version' => $version, 'releaseDate' => $releaseDate]) {
            $latest = $latestByMajor[self::majorOf($version)];
            if (isset($known[$version])) {
                $known[$version]->update($latest, $known[$version]->isInsecure());
            } else {
                $this->entityManager->persist(new CoreRelease($version, $latest, false, $releaseDate));
            }
        }
        $this->entityManager->flush();

        $this->rate();

        return count($versions);
    }

    /**
     * Re-rates every release against the synced advisories and support cycles.
     */
    public function rate(): void
    {
        $advisories = $this->advisoryRepository->findByPackageName(AdvisorySynchronizer::CORE_PACKAGE);
        $endOfLifeMajors = [];
        foreach ($this->endOfLifeReleaseRepository->findBy(['product' => 'craft-cms']) as $cycle) {
            $endOfLifeMajors[(int) $cycle->getCycle()] = $cycle->isEol();
        }

        foreach ($this->repository->findAll() as $release) {
            // a major endoflife.date doesn't list yet (e.g. right after its release) isn't end of life
            $insecure = ($endOfLifeMajors[$release->getMajor()] ?? false) || null !== AdvisoryMatcher::mostSevere($advisories, $release->getVersion());
            $release->update($release->getLatestVersion(), $insecure);
        }
        $this->entityManager->flush();
    }

    private static function majorOf(string $version): int
    {
        return (int) explode('.', $version)[0];
    }
}
