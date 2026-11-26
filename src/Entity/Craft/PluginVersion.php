<?php

declare(strict_types=1);

namespace App\Entity\Craft;

use App\Repository\Craft\PluginVersionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A stable version of a Craft plugin on Packagist, with the Craft CMS versions it installs
 * on (its "require" of craftcms/cms), synced by PluginVersionSynchronizer.
 */
#[ORM\Entity(repositoryClass: PluginVersionRepository::class)]
#[ORM\Table(name: 'craft_plugin_version')]
#[ORM\UniqueConstraint(name: 'craft_plugin_version', columns: ['package_name', 'version'])]
class PluginVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $packageName,
        #[ORM\Column(length: 64)]
        private string $version,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $releaseDate,
        // a Composer constraint like "^5.0.0"; null when the version doesn't require Craft CMS
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $craftConstraint,
    ) {
    }

    public function update(?\DateTimeImmutable $releaseDate, ?string $craftConstraint): void
    {
        $this->releaseDate = $releaseDate;
        $this->craftConstraint = $craftConstraint;
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function getCraftConstraint(): ?string
    {
        return $this->craftConstraint;
    }
}
