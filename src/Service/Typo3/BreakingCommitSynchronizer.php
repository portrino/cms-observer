<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Entity\PackageType;
use App\Entity\Typo3\PackagistVersion;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3\PackagistVersionRepository;
use App\Service\VcsPlatform\RateLimitException;
use App\Service\VcsPlatform\VcsPlatformInterface;
use App\Service\VcsPlatform\VcsPlatforms;
use App\Utility\Typo3\ExtensionUtility;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads the commits of each Packagist version of the reported TYPO3 packages since its
 * previous version on its VCS platform (GitHub, GitLab, Codeberg), and keeps those that mark a
 * breaking change (ADR-0007).
 * Commits between two versions never change, so each version is checked once; only versions
 * newer than the oldest installed one matter for an update.
 */
class BreakingCommitSynchronizer
{
    /** GitHub allows 5,000 requests an hour with a token; the hourly sync leaves room for others */
    private const int MAX_VERSIONS_PER_RUN = 300;

    public function __construct(
        private readonly VcsPlatforms $vcsPlatforms,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly PackagistVersionRepository $versionRepository,
        private readonly ExtensionVersionCatalog $versionCatalog,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return int versions checked
     */
    public function sync(): int
    {
        $checked = 0;
        // a host that declined more requests is skipped for the rest of this run, the others go on
        $limited = [];
        try {
            foreach ($this->uncheckedPairs() as [$previous, $version, $host, $repository]) {
                if ($checked >= self::MAX_VERSIONS_PER_RUN) {
                    break; // the next run goes on
                }
                if (in_array($host, $limited, true)) {
                    continue;
                }
                try {
                    $commits = $this->breakingCommits($host, $repository, $previous, $version);
                } catch (RateLimitException $e) {
                    $this->logger->warning('Stopped reading commits: {message}', ['message' => $e->getMessage()]);
                    $limited[] = $host;
                    continue;
                }
                null === $commits ? $version->recordUnreadableCommits() : $version->recordBreakingCommits($commits);
                ++$checked;
            }
        } finally {
            // also when e.g. an expired token (401) fails the run: what was read is kept
            $this->entityManager->flush();
        }

        return $checked;
    }

    /**
     * @return list<array{sha: string, subject: string, url: string, footer?: bool}>|null null when the host can't compare them
     */
    private function breakingCommits(VcsPlatformInterface $host, string $repository, PackagistVersion $previous, PackagistVersion $version): ?array
    {
        $commits = $host->commitsBetween($repository, (string) $previous->getSourceReference(), (string) $version->getSourceReference());

        if (null === $commits) {
            return null;
        }

        $breaking = [];
        foreach ($commits as $commit) {
            $footer = ExtensionUtility::hasBreakingFooter($commit['message']);
            // a merge commit repeats the subject of the commit it merges
            if (!str_starts_with($commit['subject'], 'Merge ') && ($footer || ExtensionUtility::marksBreakingCommit($commit['subject']))) {
                // the subject is shown; the footer is remembered, as the message isn't stored
                $breaking[] = ['sha' => $commit['sha'], 'subject' => $commit['subject'], 'url' => $commit['url']] + ($footer ? ['footer' => true] : []);
            }
        }

        return $breaking;
    }

    /**
     * @return iterable<array{PackagistVersion, PackagistVersion, VcsPlatformInterface, string}> previous, version, host, repository
     */
    private function uncheckedPairs(): iterable
    {
        foreach ($this->oldestInstalledVersions() as $packageName => $installed) {
            yield from $this->uncheckedPairsOf($packageName, $installed);
        }
    }

    /**
     * @return array<string, string> the oldest version installed anywhere, by Composer name
     */
    private function oldestInstalledVersions(): array
    {
        $oldest = [];
        foreach ($this->sitePackageRepository->findByPackageType(PackageType::Typo3CmsExtension) as $package) {
            $name = $this->versionCatalog->composerNameOf($package->getName(), $package->getComposerName());
            if (null !== $name && (!isset($oldest[$name]) || version_compare($package->getVersion(), $oldest[$name], '<'))) {
                $oldest[$name] = $package->getVersion();
            }
        }

        return $oldest;
    }

    /**
     * shortcut: the previous version by number, so commits backported to a maintenance branch
     * may count for the wrong line; good enough to find a "!!!" between two releases.
     *
     * @return iterable<array{PackagistVersion, PackagistVersion, VcsPlatformInterface, string}> previous, version, host, repository
     */
    private function uncheckedPairsOf(string $packageName, string $installed): iterable
    {
        $versions = $this->versionRepository->findByPackageName($packageName);
        usort($versions, static fn (PackagistVersion $a, PackagistVersion $b): int => version_compare(ltrim($a->getVersion(), 'vV'), ltrim($b->getVersion(), 'vV')));

        $previous = null;
        foreach ($versions as $version) {
            $found = null !== $previous && null !== $previous->getSourceReference() && !$version->areCommitsRead() ? $this->vcsPlatforms->find($version->getSourceUrl()) : null;
            if (null !== $found && null !== $version->getSourceReference()
                && version_compare(ltrim($version->getVersion(), 'vV'), $installed, '>')) {
                yield [$previous, $version, ...$found];
            }
            $previous = $version;
        }
    }
}
