<?php

declare(strict_types=1);

namespace App\Service\Demo;

use App\Dto\PackageReport;
use App\Dto\SiteReportRequest;
use App\Entity\Organization;
use App\Entity\Site;
use App\Entity\UpgradeCalculation;
use App\Entity\UpgradeCalculationVersion;
use App\Repository\AgencySettingsRepository;
use App\Repository\SiteRepository;
use App\Security\SiteTokenFactory;
use App\Service\SiteReportIngestor;
use App\Service\UpgradeCalculationDrafter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fills an Observer with the demo projects (DemoProjects), so it can be tried without sites
 * pushing reports: through the same report ingestion as the API, so they're rated like real ones.
 */
class DemoSeeder
{
    private const string AUTHOR = 'Demo';

    public function __construct(
        private readonly SiteReportIngestor $ingestor,
        private readonly UpgradeCalculationDrafter $drafter,
        private readonly SiteRepository $siteRepository,
        private readonly AgencySettingsRepository $settingsRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** Whether the Observer has projects already, the seed then only runs when forced */
    public function hasProjects(): bool
    {
        return $this->siteRepository->count() > 0;
    }

    /**
     * @return int the projects created, each with an older and a current report
     */
    public function seedProjects(): int
    {
        $organizations = [];
        foreach (DemoProjects::ORGANIZATIONS as $name => $description) {
            $this->entityManager->persist($organizations[$name] = new Organization($name, $description));
        }

        foreach (DemoProjects::PROJECTS as $name => [$organization, $type, $sla, $core, $php, $db, $platform, $packages]) {
            $site = new Site($name, $type, 'https://'.self::hostOf($name), new SiteTokenFactory()->generate()[1], $sla, $organizations[$organization]);
            $this->entityManager->persist($site);
            $this->entityManager->flush();

            foreach ([0, 1] as $state) {
                $this->report($site, $core[$state], $php, $db, $platform, self::versionsOf($packages, 1 + $state), $packages);
            }
        }

        return count(DemoProjects::PROJECTS);
    }

    /**
     * A saved upgrade calculation for the demo TYPO3 project, then a report with updated
     * packages: refreshing it shows the rows to check. Only with the release data synced.
     *
     * @return bool false when no newer major is known for the project yet
     */
    public function seedCalculation(): bool
    {
        $site = $this->siteRepository->findOneBy(['name' => DemoProjects::CALCULATION_PROJECT]) ?? throw new \LogicException('Seed the projects first.');
        $targets = $this->drafter->targetMajors($site);
        if ([] === $targets) {
            return false;
        }

        $calculation = new UpgradeCalculation($site, (int) $site->getCurrentCoreVersion(), $targets[0]);
        $settings = $this->settingsRepository->get();
        $this->entityManager->persist($calculation);
        $this->entityManager->persist(new UpgradeCalculationVersion(
            calculation: $calculation,
            author: self::AUTHOR,
            hourlyRate: $settings->getDefaultHourlyRate() ?? 100.0,
            managementPercent: $settings->getDefaultManagementPercent() ?? 10.0,
            final: false,
            items: $this->drafter->draft($site, $targets[0]),
            note: 'Demo',
            remarks: null,
        ));
        $this->entityManager->flush();

        [, , , $core, $php, $db, $platform, $packages] = DemoProjects::PROJECTS[DemoProjects::CALCULATION_PROJECT];
        $this->report($site, $core[1], $php, $db, $platform, DemoProjects::CALCULATION_UPDATE + self::versionsOf($packages, 2), $packages);

        return true;
    }

    /** "bergblick-tourismus-shop.example.test" for "Bergblick Tourismus – Shop" */
    private static function hostOf(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $name);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(false === $ascii ? $name : $ascii)), '-').'.example.test';
    }

    /**
     * @param array<string, array{string, string, string, ?string, string}> $packages
     * @param int                                                           $state    1 before, 2 now
     *
     * @return array<string, string> by package key
     */
    private static function versionsOf(array $packages, int $state): array
    {
        $versions = [];
        foreach ($packages as $key => $package) {
            $versions[$key] = 1 === $state ? $package[1] : $package[2];
        }

        return $versions;
    }

    /**
     * @param array<string, string>                                         $versions by package key
     * @param array<string, array{string, string, string, ?string, string}> $packages
     */
    private function report(Site $site, string $core, string $php, string $db, string $platform, array $versions, array $packages): void
    {
        $reports = [];
        foreach ($packages as $key => [$title, , , $composerName, $type]) {
            $reports[] = new PackageReport($key, $title, $versions[$key], true, $composerName, $type);
        }

        $this->ingestor->ingest($site, new SiteReportRequest(
            coreVersion: $core,
            phpVersion: $php,
            dbVersion: $db,
            clientVersion: 'demo',
            applicationContext: 'Production',
            domains: [(string) parse_url($site->getUrl(), PHP_URL_HOST)],
            dbPlatform: $platform,
            packages: $reports,
        ));
    }
}
