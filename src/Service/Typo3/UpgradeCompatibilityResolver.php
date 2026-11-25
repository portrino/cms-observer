<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Dto\UpgradeItem;
use App\Dto\UpgradeSupportPhase;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Typo3CoreRelease;
use App\Entity\Typo3TerExtensionVersion;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeNoteLevel;
use App\Entity\UpgradeSection;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3CoreReleaseRepository;
use App\Repository\Typo3TerExtensionMetaRepository;
use App\Repository\Typo3TerExtensionVersionRepository;
use App\Service\UpgradeCompatibilityResolver as CompatibilityResolver;
use App\Service\UpgradeTargets;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Judges TYPO3 extensions by the TYPO3 majors of their TER versions.
 */
#[AsTaggedItem(index: 'typo3')]
final readonly class UpgradeCompatibilityResolver implements CompatibilityResolver
{
    public function __construct(
        private SitePackageRepository $packageRepository,
        private Typo3CoreReleaseRepository $coreReleaseRepository,
        private Typo3TerExtensionVersionRepository $terVersionRepository,
        private Typo3TerExtensionMetaRepository $terMetaRepository,
    ) {
    }

    public function targetMajors(Site $site): array
    {
        return UpgradeTargets::above(
            $site->getCurrentCoreVersion(),
            array_map(static fn (Typo3CoreRelease $release): int => $release->getMajor(), $this->coreReleaseRepository->findAll()),
        );
    }

    public function packageItems(Site $site, int $targetMajor): array
    {
        $currentMajor = (int) $site->getCurrentCoreVersion();

        return array_map(
            fn (SitePackage $package): UpgradeItem => $this->packageItem($package, $currentMajor, $targetMajor),
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

    private function packageItem(SitePackage $package, int $currentMajor, int $targetMajor): UpgradeItem
    {
        $key = $package->getName();
        $installed = $package->getVersion();
        $terVersions = $this->terVersionRepository->findBy(['extensionKey' => $key]);

        [$section, $status, $target] = match (true) {
            // also a TER extension whose sync slot hasn't come up yet
            [] === $terVersions => [UpgradeSection::OwnPackages, UpgradeItemStatus::Manual, null],
            // e.g. "dev-main": can't be compared with TER versions
            !UpgradeTargets::isRelease($installed) => [UpgradeSection::Packages, UpgradeItemStatus::Manual, null],
            default => [UpgradeSection::Packages, ...$this->judgeRelease($key, $installed, $terVersions, $currentMajor, $targetMajor)],
        };

        return new UpgradeItem(
            $section,
            $package->getComposerName() ?? $this->composerNameFromTer($key) ?? $key,
            $key,
            $package->getPackageType(),
            $installed,
            $target,
            $status,
            noteLevel: UpgradeNoteLevel::forStatus($status),
        );
    }

    /**
     * The to-do for an installed TER release, and the version to update to.
     *
     * @param list<Typo3TerExtensionVersion> $terVersions
     *
     * @return array{UpgradeItemStatus, ?string}
     */
    private function judgeRelease(string $key, string $installed, array $terVersions, int $currentMajor, int $targetMajor): array
    {
        $installedMajors = $this->terVersionRepository->findOneByExtensionKeyAndVersion($key, $installed)?->getTypo3MajorVersions() ?? [];
        if (in_array($targetMajor, $installedMajors, true)) {
            return [UpgradeItemStatus::Compatible, $installed];
        }

        // newest first, so the last one is the lowest version to update to
        $forTarget = $this->terVersionRepository->findNewerCompatibleWith($key, $installed, $targetMajor);
        $forBoth = array_filter($forTarget, static fn (Typo3TerExtensionVersion $v): bool => in_array($currentMajor, $v->getTypo3MajorVersions(), true));
        if ([] !== $forBoth) {
            return [UpgradeItemStatus::UpdateFirst, end($forBoth)->getVersion()];
        }
        if ([] !== $forTarget) {
            return [UpgradeItemStatus::Update, end($forTarget)->getVersion()];
        }

        $newest = array_reduce($terVersions, static fn (?Typo3TerExtensionVersion $carry, Typo3TerExtensionVersion $v): Typo3TerExtensionVersion => null === $carry || version_compare($v->getVersion(), $carry->getVersion(), '>') ? $v : $carry);

        // without TYPO3 versions on the newest release there's nothing to judge by
        return [[] === $newest?->getTypo3MajorVersions() ? UpgradeItemStatus::Manual : UpgradeItemStatus::Blocker, null];
    }

    /**
     * @param non-empty-list<Typo3CoreRelease> $releases
     */
    private function supportPhase(int $major, array $releases): ?UpgradeSupportPhase
    {
        usort($releases, static fn (Typo3CoreRelease $a, Typo3CoreRelease $b): int => $a->getReleaseDate() <=> $b->getReleaseDate());
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
                static fn (Typo3CoreRelease $r): \DateTimeImmutable => $r->getReleaseDate(),
                array_filter($releases, static fn (Typo3CoreRelease $r): bool => str_ends_with($r->getVersion(), '.0') && (null === $lts || $r->getReleaseDate() < $lts)),
            )),
        );
    }

    /** For reports without composer names, e.g. from older clients: "georgringer/news" from TER's Packagist link */
    private function composerNameFromTer(string $key): ?string
    {
        $url = $this->terMetaRepository->findOneByExtensionKey($key)?->getPackagistUrl();

        return null !== $url && 1 === preg_match('~/packages/([^/]+/[^/?#]+)~', $url, $match) ? $match[1] : null;
    }
}
