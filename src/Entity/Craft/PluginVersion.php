<?php

declare(strict_types=1);

namespace App\Entity\Craft;

use App\Entity\AbstractPackagistVersion;
use App\Repository\Craft\PluginVersionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A stable version of a Craft plugin on Packagist, with the Craft CMS versions it installs
 * on (its "require" of craftcms/cms), synced by PluginVersionSynchronizer.
 */
#[ORM\Entity(repositoryClass: PluginVersionRepository::class)]
#[ORM\Table(name: 'craft_plugin_version')]
#[ORM\UniqueConstraint(name: 'craft_plugin_version', columns: ['package_name', 'version'])]
class PluginVersion extends AbstractPackagistVersion
{
    public function __construct(
        string $packageName,
        string $version,
        ?\DateTimeImmutable $releaseDate,
        // a Composer constraint like "^5.0.0"; null when the version doesn't require Craft CMS
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $craftConstraint,
    ) {
        parent::__construct($packageName, $version, $releaseDate);
    }

    public function update(?\DateTimeImmutable $releaseDate, ?string $craftConstraint): void
    {
        $this->setReleaseDate($releaseDate);
        $this->craftConstraint = $craftConstraint;
    }

    public function getCraftConstraint(): ?string
    {
        return $this->craftConstraint;
    }
}
