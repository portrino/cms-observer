<?php

declare(strict_types=1);

namespace App\Entity\Typo3;

use App\Entity\AbstractPackagistVersion;
use App\Repository\Typo3\PackagistVersionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A stable version of a TYPO3 package on Packagist, with the TYPO3 majors it installs on
 * (ExtensionUtility::supportedCoreMajors()), synced by PackagistVersionSynchronizer. Authors often update the TER
 * late or never, so these complement the TER versions (ADR-0006).
 */
#[ORM\Entity(repositoryClass: PackagistVersionRepository::class)]
#[ORM\Table(name: 'typo3_packagist_version')]
#[ORM\UniqueConstraint(name: 'typo3_packagist_version', columns: ['package_name', 'version'])]
class PackagistVersion extends AbstractPackagistVersion
{
    /**
     * The commits since the previous version that mention breaking changes, from GitHub
     * (CommitSynchronizer); null until checked, empty when checked and nothing was found.
     *
     * @var list<array{sha: string, subject: string, url: string}>|null
     */
    #[ORM\Column(nullable: true)]
    private ?array $breakingCommits = null;

    // GitHub couldn't compare it, e.g. a deleted repository or a moved tag: not checked again
    #[ORM\Column(options: ['default' => false])]
    private bool $commitsUnreadable = false;

    /**
     * @param list<int>|null $typo3Majors null when the version requires no core package
     */
    public function __construct(
        string $packageName,
        string $version,
        ?\DateTimeImmutable $releaseDate,
        #[ORM\Column(nullable: true)]
        private ?array $typo3Majors,
        // the repository and commit of the version, e.g. "https://github.com/b13/http2.git"
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $sourceUrl = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $sourceReference = null,
    ) {
        parent::__construct($packageName, $version, $releaseDate);
    }

    /**
     * @param list<int>|null $typo3Majors
     */
    public function update(?\DateTimeImmutable $releaseDate, ?array $typo3Majors, ?string $sourceUrl, ?string $sourceReference): void
    {
        $this->setReleaseDate($releaseDate);
        $this->typo3Majors = $typo3Majors;
        if ($sourceReference !== $this->sourceReference) {
            // a moved tag: its commits are checked anew
            $this->breakingCommits = null;
            $this->commitsUnreadable = false;
        }
        $this->sourceUrl = $sourceUrl;
        $this->sourceReference = $sourceReference;
    }

    /**
     * @param list<array{sha: string, subject: string, url: string}> $commits
     */
    public function recordBreakingCommits(array $commits): void
    {
        $this->breakingCommits = $commits;
    }

    /** Its commits couldn't be read: still unknown, but not asked for again */
    public function recordUnreadableCommits(): void
    {
        $this->commitsUnreadable = true;
    }

    /** Whether the sync is done with it, whatever it found */
    public function areCommitsRead(): bool
    {
        return null !== $this->breakingCommits || $this->commitsUnreadable;
    }

    /**
     * @return list<array{sha: string, subject: string, url: string}>|null null until checked, and when unreadable
     */
    public function getBreakingCommits(): ?array
    {
        return $this->breakingCommits;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function getSourceReference(): ?string
    {
        return $this->sourceReference;
    }

    /**
     * @return list<int>|null null when nothing says, e.g. no core package required
     */
    public function getTypo3Majors(): ?array
    {
        return $this->typo3Majors;
    }
}
