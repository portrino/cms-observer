<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Entity\PackageType;
use App\Entity\Typo3\PackagistVersion;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3\PackagistVersionRepository;
use App\Service\GitHubApiClient;
use App\Service\GitHubRateLimitException;
use App\Utility\Typo3\ExtensionUtility;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads the commits of each Packagist version of the reported TYPO3 packages since its
 * previous version on GitHub, and keeps those that mention a breaking change (ADR-0007).
 * Commits between two versions never change, so each version is checked once; only versions
 * newer than the oldest installed one matter for an update.
 */
class BreakingCommitSynchronizer
{
    /** GitHub allows 5,000 requests an hour with a token; the hourly sync leaves room for others */
    private const int MAX_VERSIONS_PER_RUN = 300;

    public function __construct(
        private readonly GitHubApiClient $gitHub,
        private readonly SitePackageRepository $sitePackageRepository,
        private readonly PackagistVersionRepository $versionRepository,
        private readonly ExtensionVersionCatalog $versionCatalog,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return int|null versions checked, null without a GitHub token
     */
    public function sync(): ?int
    {
        if (!$this->gitHub->isConfigured()) {
            return null;
        }

        $checked = 0;
        try {
            foreach ($this->uncheckedPairs() as [$previous, $version, $repository]) {
                if ($checked >= self::MAX_VERSIONS_PER_RUN) {
                    break; // the next run goes on
                }
                $commits = $this->breakingCommits($repository, $previous, $version);
                null === $commits ? $version->recordUnreadableCommits() : $version->recordBreakingCommits($commits);
                ++$checked;
            }
        } catch (GitHubRateLimitException $e) {
            // the versions not checked yet stay unchecked, the next run goes on
            $this->logger->warning('Stopped reading commits from GitHub: {message}', ['message' => $e->getMessage()]);
        } finally {
            // also when e.g. an expired token (401) fails the run: what was read is kept
            $this->entityManager->flush();
        }

        return $checked;
    }

    /**
     * @return list<array{sha: string, subject: string, url: string}>|null null when GitHub can't compare them
     */
    private function breakingCommits(string $repository, PackagistVersion $previous, PackagistVersion $version): ?array
    {
        $commits = $this->gitHub->commitsBetween($repository, (string) $previous->getSourceReference(), (string) $version->getSourceReference());

        return null === $commits ? null : array_values(array_filter(
            $commits,
            // a merge commit repeats the subject of the commit it merges
            static fn (array $commit): bool => !str_starts_with($commit['subject'], 'Merge ') && ExtensionUtility::mentionsBreakingChange($commit['subject']),
        ));
    }

    /**
     * @return iterable<array{PackagistVersion, PackagistVersion, string}> previous, version, repository
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
     * @return iterable<array{PackagistVersion, PackagistVersion, string}> previous, version, repository
     */
    private function uncheckedPairsOf(string $packageName, string $installed): iterable
    {
        $versions = $this->versionRepository->findByPackageName($packageName);
        usort($versions, static fn (PackagistVersion $a, PackagistVersion $b): int => version_compare(ltrim($a->getVersion(), 'vV'), ltrim($b->getVersion(), 'vV')));

        $previous = null;
        foreach ($versions as $version) {
            $repository = GitHubApiClient::repositoryOf($version->getSourceUrl());
            if (null !== $previous && !$version->areCommitsRead() && null !== $repository
                && null !== $previous->getSourceReference() && null !== $version->getSourceReference()
                && version_compare(ltrim($version->getVersion(), 'vV'), $installed, '>')) {
                yield [$previous, $version, $repository];
            }
            $previous = $version;
        }
    }
}
