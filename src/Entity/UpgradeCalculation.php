<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UpgradeCalculationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A major-upgrade calculation of one project; its content lives in immutable versions (ADR-0004).
 */
#[ORM\Entity(repositoryClass: UpgradeCalculationRepository::class)]
class UpgradeCalculation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, UpgradeCalculationVersion> */
    #[ORM\OneToMany(targetEntity: UpgradeCalculationVersion::class, mappedBy: 'calculation')]
    #[ORM\OrderBy(['createdAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $versions;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Site::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Site $site,
        // the core major when the calculation was started
        #[ORM\Column]
        private int $fromMajor,
        #[ORM\Column]
        private int $targetMajor,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->versions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getFromMajor(): int
    {
        return $this->fromMajor;
    }

    public function getTargetMajor(): int
    {
        return $this->targetMajor;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Newest first.
     *
     * @return list<UpgradeCalculationVersion>
     */
    public function getVersions(): array
    {
        return array_values($this->versions->toArray());
    }

    public function getLatestVersion(): ?UpgradeCalculationVersion
    {
        $first = $this->versions->first();

        return false !== $first ? $first : null;
    }

    /** Called by the version's constructor only */
    public function addVersion(UpgradeCalculationVersion $version): void
    {
        $this->versions->add($version);
    }
}
