<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A stable version of a Composer package on Packagist, synced per CMS
 * (AbstractPackagistVersionSynchronizer); each CMS adds what it reads from the "require".
 */
#[ORM\MappedSuperclass]
abstract class AbstractPackagistVersion
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
    ) {
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

    protected function setReleaseDate(?\DateTimeImmutable $releaseDate): void
    {
        $this->releaseDate = $releaseDate;
    }
}
