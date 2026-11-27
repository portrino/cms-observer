<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Dto\UpgradeItem;
use App\Dto\UpgradeSupportPhase;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Typo3\CoreRelease;
use App\Entity\Typo3\TerExtensionVersion;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3\CoreReleaseRepository;
use App\Repository\Typo3\TerExtensionMetaRepository;
use App\Repository\Typo3\TerExtensionVersionRepository;
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
        private CoreReleaseRepository $coreReleaseRepository,
        private TerExtensionVersionRepository $terVersionRepository,
        private TerExtensionMetaRepository $terMetaRepository,
        private UpgradeNoteDrafter $noteDrafter,
    ) {
    }

    public function targetMajors(Site $site): array
    {
        return UpgradeTargets::above(
            $site->getCurrentCoreVersion(),
            array_map(static fn (CoreRelease $release): int => $release->getMajor(), $this->coreReleaseRepository->findAll()),
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

        $note = $this->noteDrafter->draft($status, $installed, $target, $terVersions, $targetMajor);

        return new UpgradeItem(
            $section,
            $package->getComposerName() ?? $this->composerNameFromTer($key) ?? $key,
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
     * The to-do for an installed TER release, and the version to update to.
     *
     * @param list<TerExtensionVersion> $terVersions
     *
     * @return array{UpgradeItemStatus, ?string}
     */
    private function judgeRelease(string $key, string $installed, array $terVersions, int $currentMajor, int $targetMajor): array
    {
        // newest first, so the last one is the lowest version to update to
        $forTarget = $this->terVersionRepository->findNewerCompatibleWith($key, $installed, $targetMajor);
        $installedMajors = $this->terVersionRepository->findOneByExtensionKeyAndVersion($key, $installed)?->getTypo3MajorVersions() ?? [];
        if (in_array($targetMajor, $installedMajors, true)) {
            // already compatible: the newest version for the target major, if there's a newer one
            return [UpgradeItemStatus::Compatible, ($forTarget[0] ?? null)?->getVersion() ?? $installed];
        }

        $forBoth = array_filter($forTarget, static fn (TerExtensionVersion $v): bool => in_array($currentMajor, $v->getTypo3MajorVersions(), true));
        if ([] !== $forBoth) {
            return [UpgradeItemStatus::UpdateFirst, end($forBoth)->getVersion()];
        }
        if ([] !== $forTarget) {
            return [UpgradeItemStatus::Update, end($forTarget)->getVersion()];
        }

        $newest = array_reduce($terVersions, static fn (?TerExtensionVersion $carry, TerExtensionVersion $v): TerExtensionVersion => null === $carry || version_compare($v->getVersion(), $carry->getVersion(), '>') ? $v : $carry);

        // without TYPO3 versions on the newest release there's nothing to judge by
        return [[] === $newest?->getTypo3MajorVersions() ? UpgradeItemStatus::Manual : UpgradeItemStatus::Blocker, null];
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

    /** For reports without composer names, e.g. from older clients: "georgringer/news" from TER's Packagist link */
    private function composerNameFromTer(string $key): ?string
    {
        $url = $this->terMetaRepository->findOneByExtensionKey($key)?->getPackagistUrl();

        return null !== $url && 1 === preg_match('~/packages/([^/]+/[^/?#]+)~', $url, $match) ? $match[1] : null;
    }
}
