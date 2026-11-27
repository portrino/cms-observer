<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Dto\Typo3\ExtensionVersion;
use App\Dto\UpgradeItem;
use App\Dto\UpgradeSupportPhase;
use App\Entity\AgencySettings;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Typo3\CoreRelease;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;
use App\Repository\AgencySettingsRepository;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3\CoreReleaseRepository;
use App\Service\UpgradeCompatibilityResolver as CompatibilityResolver;
use App\Utility\VersionUtility;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Judges TYPO3 extensions by the TYPO3 majors of their TER and Packagist versions.
 */
#[AsTaggedItem(index: 'typo3')]
final readonly class UpgradeCompatibilityResolver implements CompatibilityResolver
{
    public function __construct(
        private SitePackageRepository $packageRepository,
        private CoreReleaseRepository $coreReleaseRepository,
        private ExtensionVersionCatalog $versionCatalog,
        private AgencySettingsRepository $agencySettingsRepository,
        private UpgradeNoteDrafter $noteDrafter,
    ) {
    }

    public function targetMajors(Site $site): array
    {
        return VersionUtility::majorsAbove(
            $site->getCurrentCoreVersion(),
            array_map(static fn (CoreRelease $release): int => $release->getMajor(), $this->coreReleaseRepository->findAll()),
        );
    }

    public function packageItems(Site $site, int $targetMajor): array
    {
        $currentMajor = (int) $site->getCurrentCoreVersion();
        $settings = $this->agencySettingsRepository->get();

        return array_map(
            fn (SitePackage $package): UpgradeItem => $this->packageItem($package, $currentMajor, $targetMajor, $settings),
            $this->packageRepository->findBy(['site' => $site], ['name' => 'ASC']),
        );
    }

    public function supportPhases(int $fromMajor, int $targetMajor): array
    {
        $releasesByMajor = [];
        foreach ($this->coreReleaseRepository->findAll() as $release) {
            if ($release->getMajor() >= $fromMajor && $release->getMajor() <= $targetMajor && null !== $release->getMaintainedUntil()) {
                $releasesByMajor[$release->getMajor()][] = $release;
            }
        }
        ksort($releasesByMajor);

        $phases = [];
        foreach ($releasesByMajor as $major => $releases) {
            $phase = $this->supportPhase($major, $releases);
            if (null !== $phase) {
                $phases[] = $phase;
            }
        }

        return $phases;
    }

    private function packageItem(SitePackage $package, int $currentMajor, int $targetMajor, AgencySettings $settings): UpgradeItem
    {
        $key = $package->getName();
        $installed = $package->getVersion();
        $composerName = $this->versionCatalog->composerNameOf($key, $package->getComposerName());
        $versions = $this->versionCatalog->versionsOf($key, $composerName);
        // the agency's own packages are its work to upgrade, though published like third-party ones
        $section = [] === $versions || $settings->isAgencyPackage($composerName) ? UpgradeSection::OwnPackages : UpgradeSection::Packages;

        [$status, $target] = match (true) {
            // neither in the TER nor on Packagist; also a package whose sync hasn't come up yet
            [] === $versions => [UpgradeItemStatus::Manual, null],
            // e.g. "dev-main": can't be compared with released versions
            !VersionUtility::isRelease($installed) => [UpgradeItemStatus::Manual, null],
            default => self::judgeRelease($installed, $versions, $currentMajor, $targetMajor),
        };

        $note = $this->noteDrafter->draft($status, $installed, $target, $versions, $targetMajor);

        return new UpgradeItem(
            $section,
            $composerName ?? $key,
            $key,
            $package->getPackageType(),
            $installed,
            $target,
            $status,
            minHours: $note['hours'],
            maxHours: $note['hours'],
            notes: $note['notes'],
            noteLevel: $note['noteLevel'],
        );
    }

    /**
     * The to-do for an installed release, and the version to update to.
     *
     * @param non-empty-list<ExtensionVersion> $versions newest first, from the TER and Packagist
     *
     * @return array{UpgradeItemStatus, ?string}
     */
    private static function judgeRelease(string $installed, array $versions, int $currentMajor, int $targetMajor): array
    {
        // newest first, so the last one is the lowest version to update to
        $forTarget = array_values(array_filter($versions, static fn (ExtensionVersion $v): bool => version_compare($v->version, $installed, '>') && $v->supports($targetMajor)));
        if (true === array_find($versions, static fn (ExtensionVersion $v): bool => $v->version === $installed)?->supports($targetMajor)) {
            // already compatible: the newest version for the target major, if there's a newer one
            return [UpgradeItemStatus::Compatible, [] !== $forTarget ? $forTarget[0]->version : $installed];
        }

        $forBoth = array_filter($forTarget, static fn (ExtensionVersion $v): bool => $v->supports($currentMajor));
        if ([] !== $forBoth) {
            return [UpgradeItemStatus::UpdateFirst, end($forBoth)->version];
        }
        if ([] !== $forTarget) {
            return [UpgradeItemStatus::Update, end($forTarget)->version];
        }

        // without TYPO3 versions on the newest release there's nothing to judge by
        return [[] === $versions[0]->typo3MajorVersions ? UpgradeItemStatus::Manual : UpgradeItemStatus::Blocker, null];
    }

    /**
     * @param non-empty-list<CoreRelease> $releases
     */
    private function supportPhase(int $major, array $releases): ?UpgradeSupportPhase
    {
        usort($releases, static fn (CoreRelease $a, CoreRelease $b): int => $a->getReleaseDate() <=> $b->getReleaseDate());
        $any = $releases[0];
        $maintainedUntil = $any->getMaintainedUntil();
        if (null === $maintainedUntil) {
            return null;
        }
        $lts = $any->getLtsReleaseDate();
        // TYPO3's priority bugfix phase: the last 1.5 years of the free updates
        $prioritySince = $maintainedUntil->modify('-18 months');

        return new UpgradeSupportPhase(
            $major,
            $releases[0]->getReleaseDate(),
            $maintainedUntil,
            $any->getEltsUntil(),
            $lts,
            null !== $lts && $prioritySince < $lts ? $lts : $prioritySince,
            // sprint releases: the x.y.0 versions before the LTS one
            array_values(array_map(
                static fn (CoreRelease $r): \DateTimeImmutable => $r->getReleaseDate(),
                array_filter($releases, static fn (CoreRelease $r): bool => str_ends_with($r->getVersion(), '.0') && (null === $lts || $r->getReleaseDate() < $lts)),
            )),
        );
    }
}
