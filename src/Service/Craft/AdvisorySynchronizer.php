<?php

declare(strict_types=1);

namespace App\Service\Craft;

use App\Entity\Craft\Advisory;
use App\Entity\PackageType;
use App\Entity\SitePackage;
use App\Repository\Craft\AdvisoryRepository;
use App\Repository\SitePackageRepository;
use App\Service\PackagistApiClient;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Syncs the Packagist advisories of Craft CMS and of every plugin a Craft site reports,
 * then re-rates the core releases, which are insecure when an advisory affects them.
 */
class AdvisorySynchronizer
{
    public const string CORE_PACKAGE = 'craftcms/cms';

    public function __construct(
        private readonly PackagistApiClient $apiClient,
        private readonly AdvisoryRepository $repository,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly CoreReleaseSynchronizer $coreReleaseSynchronizer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int number of advisories synced
     */
    public function sync(): int
    {
        $packageNames = $this->packageNames();
        $known = [];
        foreach ($this->repository->findBy(['packageName' => $packageNames]) as $advisory) {
            $known[$advisory->getAdvisoryId()] = $advisory;
        }

        $synced = 0;
        foreach ($this->apiClient->advisories($packageNames) as $packageName => $advisories) {
            foreach ($advisories as $data) {
                $id = $this->save($known, $packageName, $data);
                unset($known[$id]);
                $synced += null !== $id ? 1 : 0;
            }
        }
        // withdrawn upstream: an advisory that's gone mustn't keep a version insecure
        foreach ($known as $withdrawn) {
            $this->entityManager->remove($withdrawn);
        }
        $this->entityManager->flush();

        $this->coreReleaseSynchronizer->rate();

        return $synced;
    }

    /**
     * Craft CMS itself and the reported plugins that have a Composer name.
     *
     * @return list<string>
     */
    private function packageNames(): array
    {
        $names = array_map(static fn (SitePackage $package): ?string => $package->getComposerName(), $this->sitePackageRepository->findByPackageType(PackageType::CraftPlugin));

        return array_values(array_unique([self::CORE_PACKAGE, ...array_filter($names, is_string(...))]));
    }

    /**
     * @param array<string, Advisory> $known by advisory id
     * @param array<string, mixed>    $data
     *
     * @return string|null the advisory id, null for an entry without one
     */
    private function save(array $known, string $packageName, array $data): ?string
    {
        $id = self::text($data['advisoryId'] ?? null);
        if (null === $id) {
            return null;
        }
        $title = self::text($data['title'] ?? null) ?? $id;
        $link = self::text($data['link'] ?? null) ?? 'https://packagist.org/packages/'.$packageName.'/advisories';
        $affected = self::text($data['affectedVersions'] ?? null) ?? '';
        $cve = self::text($data['cve'] ?? null);
        $severity = self::text($data['severity'] ?? null);

        if (isset($known[$id])) {
            $known[$id]->update($title, $link, $affected, $cve, $severity);
        } else {
            $this->entityManager->persist(new Advisory($id, $packageName, $title, $link, $affected, $cve, $severity));
        }

        return $id;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
