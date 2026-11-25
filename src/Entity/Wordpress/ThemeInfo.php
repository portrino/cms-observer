<?php

declare(strict_types=1);

namespace App\Entity\Wordpress;

use App\Repository\Wordpress\ThemeInfoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * The latest version of a wordpress.org theme, synced by Wordpress\ThemeSynchronizer to rate
 * reported themes as outdated. Unlike plugins no version history: themes only need "a newer
 * version exists and runs on the site's WordPress".
 */
#[ORM\Entity(repositoryClass: ThemeInfoRepository::class)]
#[ORM\Table(name: 'wordpress_theme_info')]
class ThemeInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(length: 255, unique: true)]
        private string $slug,
        #[ORM\Column(length: 32)]
        private string $latestVersion,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $requiresWpVersion,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(string $latestVersion, ?string $requiresWpVersion): void
    {
        $this->latestVersion = $latestVersion;
        $this->requiresWpVersion = $requiresWpVersion;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLatestVersion(): string
    {
        return $this->latestVersion;
    }

    public function getRequiresWpVersion(): ?string
    {
        return $this->requiresWpVersion;
    }
}
