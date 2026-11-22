<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UpgradeItem;
use App\Dto\UpgradeSupportPhase;
use App\Entity\Site;
use App\Entity\Typo3CoreRelease;
use App\Entity\Typo3TerExtensionVersion;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeNoteLevel;
use App\Entity\UpgradeSection;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3CoreReleaseRepository;
use App\Repository\Typo3TerExtensionMetaRepository;
use App\Repository\Typo3TerExtensionVersionRepository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Judges TYPO3 extensions by the TYPO3 majors of their TER versions.
 */
#[AsTaggedItem(index: 'typo3')]
final class Typo3UpgradeCompatibilityResolver implements UpgradeCompatibilityResolver
{
    public function __construct(
        private readonly SitePackageRepository $packageRepository,
        private readonly Typo3CoreReleaseRepository $coreReleaseRepository,
        private readonly Typo3TerExtensionVersionRepository $terVersionRepository,
        private readonly Typo3TerExtensionMetaRepository $terMetaRepository,
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
        $items = [];
        foreach ($this->packageRepository->findBy(['site' => $site], ['name' => 'ASC']) as $package) {
            $key = $package->getName();
            $installed = $package->getVersion();
            $label = $package->getComposerName() ?? $this->composerNameFromTer($key) ?? $key;
            $item = static fn (UpgradeSection $section, UpgradeItemStatus $status, ?string $target = null): UpgradeItem => new UpgradeItem(
                $section, $label, $key, $package->getPackageType(), $installed, $target, $status, noteLevel: UpgradeNoteLevel::forStatus($status),
            );

            $terVersions = $this->terVersionRepository->findBy(['extensionKey' => $key]);
            if ([] === $terVersions) {
                // also a TER extension whose sync slot hasn't come up yet
                $items[] = $item(UpgradeSection::OwnPackages, UpgradeItemStatus::Manual);
                continue;
            }
            if (!UpgradeTargets::isRelease($installed)) {
                // e.g. "dev-main": can't be compared with TER versions
                $items[] = $item(UpgradeSection::Packages, UpgradeItemStatus::Manual);
                continue;
            }

            $installedMajors = $this->terVersionRepository->findOneByExtensionKeyAndVersion($key, $installed)?->getTypo3MajorVersions() ?? [];
            if (in_array($targetMajor, $installedMajors, true)) {
                $items[] = $item(UpgradeSection::Packages, UpgradeItemStatus::Compatible, $installed);
                continue;
            }

            // newest first, so the last one is the lowest version to update to
            $forTarget = $this->terVersionRepository->findNewerCompatibleWith($key, $installed, $targetMajor);
            $forBoth = array_filter($forTarget, static fn (Typo3TerExtensionVersion $v): bool => in_array($currentMajor, $v->getTypo3MajorVersions(), true));
            if ([] !== $forBoth) {
                $items[] = $item(UpgradeSection::Packages, UpgradeItemStatus::UpdateFirst, end($forBoth)->getVersion());
            } elseif ([] !== $forTarget) {
                $items[] = $item(UpgradeSection::Packages, UpgradeItemStatus::Update, end($forTarget)->getVersion());
            } else {
                $newest = array_reduce($terVersions, static fn (?Typo3TerExtensionVersion $carry, Typo3TerExtensionVersion $v): Typo3TerExtensionVersion => null === $carry || version_compare($v->getVersion(), $carry->getVersion(), '>') ? $v : $carry);
                // without TYPO3 versions on the newest release there's nothing to judge by
                $items[] = $item(UpgradeSection::Packages, [] === $newest?->getTypo3MajorVersions() ? UpgradeItemStatus::Manual : UpgradeItemStatus::Blocker);
            }
        }

        return $items;
    }

    public function supportPhases(int $fromMajor, int $targetMajor): array
    {
        $byMajor = [];
        foreach ($this->coreReleaseRepository->findAll() as $release) {
            $major = $release->getMajor();
            if ($major < $fromMajor || $major > $targetMajor || null === $release->getMaintainedUntil()) {
                continue;
            }
            $known = $byMajor[$major] ?? null;
            $byMajor[$major] = new UpgradeSupportPhase(
                $major,
                null === $known || $release->getReleaseDate() < $known->start ? $release->getReleaseDate() : $known->start,
                $release->getMaintainedUntil(),
                $release->getEltsUntil() ?? $known?->extendedUntil,
            );
        }
        ksort($byMajor);

        return array_values($byMajor);
    }

    /** For reports without composer names, e.g. from older clients: "georgringer/news" from TER's Packagist link */
    private function composerNameFromTer(string $key): ?string
    {
        $url = $this->terMetaRepository->findOneByExtensionKey($key)?->getPackagistUrl();

        return null !== $url && 1 === preg_match('~/packages/([^/]+/[^/?#]+)~', $url, $match) ? $match[1] : null;
    }
}
