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

    public function setUpgradeCalculationDefaults(?float $hourlyRate, ?float $managementPercent): void
    {
        $this->defaultHourlyRate = $hourlyRate;
        $this->defaultManagementPercent = $managementPercent;
    }
}
