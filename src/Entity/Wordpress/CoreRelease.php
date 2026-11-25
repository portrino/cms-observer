<?php

declare(strict_types=1);

namespace App\Entity\Wordpress;

use App\Entity\CoreReleaseInterface;
use App\Repository\Wordpress\CoreReleaseRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * WordPress core release. Status (insecure/latest) is synced from
 * api.wordpress.org/core/stable-check/1.0/; the release date has no
 * equivalent in that feed, so it's scraped separately from
 * core.svn.wordpress.org's tags/ directory the same way plugin release
 * dates are (Wordpress\ApiClient::getTagDates()) - see
 * Wordpress\CoreReleaseSynchronizer.
 */
#[ORM\Entity(repositoryClass: CoreReleaseRepository::class)]
#[ORM\Table(name: 'wordpress_core_release')]
class CoreRelease implements CoreReleaseInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(length: 32, unique: true)]
        private string $version,
        #[ORM\Column(length: 32)]
        private string $latestVersion,
        #[ORM\Column]
        private bool $insecure,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $releaseDate,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(string $latestVersion, bool $insecure, ?\DateTimeImmutable $releaseDate): void
    {
        $this->latestVersion = $latestVersion;
        $this->insecure = $insecure;
        // A release's date is an immutable historical fact once known - don't
        // let a run that couldn't resolve it (e.g. tag missing from this
        // batch) blank out a previously-resolved date.
        if (null !== $releaseDate) {
            $this->releaseDate = $releaseDate;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Leading major component, e.g. 6 for "6.5.2". Same "leading integer"
     * convention used for TYPO3 majors, applied to WordPress's own numbering.
     */
    public function getMajor(): int
    {
        return (int) explode('.', $this->version)[0];
    }

    public function getLatestVersion(): string
    {
        return $this->latestVersion;
    }

    public function isLatest(): bool
    {
        return $this->version === $this->latestVersion;
    }

    public function isInsecure(): bool
    {
        return $this->insecure;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
