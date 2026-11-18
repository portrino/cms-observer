<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SitePackageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SitePackageRepository::class)]
#[ORM\UniqueConstraint(name: 'site_package_name', columns: ['site_id', 'name'])]
class SitePackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: PackageType::class)]
    private readonly PackageType $packageType;

    /**
     * Set only by the Observer's own version cross-reference (TER for TYPO3
     * extensions, WordPress.org's plugin API for WordPress plugins), never
     * by the client - see SitePackageTerEvaluator/SitePackageWordpressEvaluator.
     */
    #[ORM\Column(nullable: true)]
    private ?bool $insecure = null;

    #[ORM\Column(nullable: true)]
    private ?bool $outdated = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $latestVersion = null;

    #[ORM\ManyToOne(targetEntity: Typo3ExtensionAdvisory::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Typo3ExtensionAdvisory $matchedAdvisory = null;

    // most severe Wordfence vulnerability affecting this WordPress plugin version
    #[ORM\ManyToOne(targetEntity: WordpressPluginVulnerability::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WordpressPluginVulnerability $matchedWordpressVulnerability = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Site::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Site $site,
        #[ORM\Column(length: 255)]
        private string $name,
        #[ORM\Column(length: 255)]
        private string $title,
        #[ORM\Column(length: 32)]
        private string $version,
        #[ORM\Column]
        private bool $active,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $composerName = null,
    ) {
        $this->packageType = PackageType::fromCmsType($site->getType());
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getPackageType(): PackageType
    {
        return $this->packageType;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getComposerName(): ?string
    {
        return $this->composerName;
    }

    public function isInsecure(): ?bool
    {
        return $this->insecure;
    }

    public function isOutdated(): ?bool
    {
        return $this->outdated;
    }

    public function getLatestVersion(): ?string
    {
        return $this->latestVersion;
    }

    public function setVersionStatus(?bool $insecure, ?bool $outdated, ?string $latestVersion): void
    {
        $this->insecure = $insecure;
        $this->outdated = $outdated;
        $this->latestVersion = $latestVersion;
    }

    public function getMatchedAdvisory(): ?Typo3ExtensionAdvisory
    {
        return $this->matchedAdvisory;
    }

    public function setMatchedAdvisory(?Typo3ExtensionAdvisory $advisory): void
    {
        $this->matchedAdvisory = $advisory;
    }

    public function getMatchedWordpressVulnerability(): ?WordpressPluginVulnerability
    {
        return $this->matchedWordpressVulnerability;
    }

    public function setMatchedWordpressVulnerability(?WordpressPluginVulnerability $vulnerability): void
    {
        $this->matchedWordpressVulnerability = $vulnerability;
    }

    public function update(string $title, string $version, bool $active, ?string $composerName = null): void
    {
        $this->title = $title;
        $this->active = $active;
        $this->composerName = $composerName;
        $this->updatedAt = new \DateTimeImmutable();

        if ($version !== $this->version) {
            // The old insecure/outdated verdict was for a different version - stale
            // until the next TER sync re-resolves it, so don't keep showing it.
            $this->version = $version;
            $this->setVersionStatus(null, null, null);
        }
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
