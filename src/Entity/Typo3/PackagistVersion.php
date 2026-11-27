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
     * @param list<int>|null $typo3Majors null when the version requires no core package
     */
    public function __construct(
        string $packageName,
        string $version,
        ?\DateTimeImmutable $releaseDate,
        #[ORM\Column(nullable: true)]
        private ?array $typo3Majors,
    ) {
        parent::__construct($packageName, $version, $releaseDate);
    }

    /**
     * @param list<int>|null $typo3Majors
     */
    public function update(?\DateTimeImmutable $releaseDate, ?array $typo3Majors): void
    {
        $this->setReleaseDate($releaseDate);
        $this->typo3Majors = $typo3Majors;
    }

    /**
     * @return list<int>|null null when nothing says, e.g. no core package required
     */
    public function getTypo3Majors(): ?array
    {
        return $this->typo3Majors;
    }
}
