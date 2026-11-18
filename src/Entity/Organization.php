<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Site> */
    #[ORM\OneToMany(targetEntity: Site::class, mappedBy: 'organization')]
    private Collection $sites;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $name,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $website = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->sites = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updateDetails(string $name, ?string $description, ?string $website): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->website = $website;
    }

    /**
     * @return Collection<int, Site>
     */
    public function getSites(): Collection
    {
        return $this->sites;
    }
}
