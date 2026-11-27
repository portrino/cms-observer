<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AgencySettingsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Settings of the agency running the Observer: a single row, edited on the settings page.
 */
#[ORM\Entity(repositoryClass: AgencySettingsRepository::class)]
class AgencySettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // what a project's first upgrade calculation starts with
    #[ORM\Column(nullable: true)]
    private ?float $defaultHourlyRate = null;

    #[ORM\Column(nullable: true)]
    private ?float $defaultManagementPercent = null;

    // the agency's own vendors on Packagist, comma-separated, e.g. "portrino"
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $packagistVendors = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDefaultHourlyRate(): ?float
    {
        return $this->defaultHourlyRate;
    }

    public function getDefaultManagementPercent(): ?float
    {
        return $this->defaultManagementPercent;
    }

    /**
     * @return list<string> lowercase
     */
    public function getPackagistVendors(): array
    {
        // stored as setPackagistVendors() left it
        return null !== $this->packagistVendors ? explode(', ', $this->packagistVendors) : [];
    }

    /**
     * @param list<string> $vendors
     */
    public function setPackagistVendors(array $vendors): void
    {
        $vendors = array_unique(array_filter(array_map(static fn (string $vendor): string => strtolower(trim($vendor)), $vendors), static fn (string $vendor): bool => '' !== $vendor));
        $this->packagistVendors = [] !== $vendors ? implode(', ', $vendors) : null;
    }

    /**
     * Whether a package is the agency's own, published or not: its Composer vendor is one of
     * the agency's, so its upgrade is the agency's work like that of a site's own extensions.
     */
    public function isAgencyPackage(?string $composerName): bool
    {
        return null !== $composerName && in_array(strtolower(explode('/', $composerName)[0]), $this->getPackagistVendors(), true);
    }

    public function setUpgradeCalculationDefaults(?float $hourlyRate, ?float $managementPercent): void
    {
        $this->defaultHourlyRate = $hourlyRate;
        $this->defaultManagementPercent = $managementPercent;
    }
}
