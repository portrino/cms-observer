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
     * @return int number of releases synced; none until the support cycles and the advisories
     *             were synced once, so a site's core stays unknown instead of looking secure
     */
    public function sync(): int
    {
        if (!$this->canRate()) {
            return 0;
        }

        $versions = $this->apiClient->versions(AdvisorySynchronizer::CORE_PACKAGE) ?? [];

        $latestByMajor = self::latestByMajor(array_column($versions, 'version'));

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

    /**
     * @param list<string> $versions
     *
     * @return array<int, string> the newest version of each major
     */
    private static function latestByMajor(array $versions): array
    {
        $latest = [];
        foreach ($versions as $version) {
            $major = self::majorOf($version);
            if (!isset($latest[$major]) || version_compare($version, $latest[$major], '>')) {
                $latest[$major] = $version;
            }
        }

        return $latest;
    }

    private function canRate(): bool
    {
        return [] !== $this->endOfLifeReleaseRepository->findBy(['product' => 'craft-cms'], limit: 1)
            && [] !== $this->advisoryRepository->findByPackageName(AdvisorySynchronizer::CORE_PACKAGE);
    }

    private static function majorOf(string $version): int
    {
        return (int) explode('.', $version)[0];
    }
}
