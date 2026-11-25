<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WordpressPluginVersionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Caches one (plugin slug, version) pair's release date and minimum-
 * WordPress requirement, scraped from the plugin's SVN mirror
 * (Wordpress\PluginSynchronizer / Wordpress\ApiClient) since WordPress.org's
 * plugin info API only exposes this for the single latest version. There's
 * no per-version "comment"/changelog field here (unlike
 * Typo3TerExtensionVersion's uploadComment) - no reliable structured source
 * for it exists on WordPress.org.
 */
#[ORM\Entity(repositoryClass: WordpressPluginVersionRepository::class)]
#[ORM\UniqueConstraint(name: 'wordpress_plugin_version', columns: ['slug', 'version'])]
class WordpressPluginVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $fetchedAt;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $slug,
        #[ORM\Column(length: 32)]
        private string $version,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $releaseDate,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $requiresWpVersion,
    ) {
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function update(?\DateTimeImmutable $releaseDate, ?string $requiresWpVersion): void
    {
        $this->releaseDate = $releaseDate;
        $this->requiresWpVersion = $requiresWpVersion;
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function getRequiresWpVersion(): ?string
    {
        return $this->requiresWpVersion;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }
}
