<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Typo3\ExtensionAdvisory;
use App\Entity\Wordpress\PluginVulnerability;
use App\Repository\SitePackageRepository;
use App\Service\PackageUpdate;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SitePackageRepository::class)]
// a WordPress theme and plugin may share a slug
#[ORM\UniqueConstraint(name: 'site_package_name', columns: ['site_id', 'package_type', 'name'])]
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
     * by the client - see SitePackageTerEvaluator/Wordpress\SitePackageEvaluator.
     */
    #[ORM\Column(nullable: true)]
    private ?bool $insecure = null;

    #[ORM\Column(nullable: true)]
    private ?bool $outdated = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $latestVersion = null;

    // a newer version that needs a newer core than the site's, e.g. core "14" with package "13.0.0"
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $newerCoreVersion = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $newerCorePackageVersion = null;

    #[ORM\ManyToOne(targetEntity: ExtensionAdvisory::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ExtensionAdvisory $matchedAdvisory = null;

    // most severe Wordfence vulnerability affecting this WordPress plugin version
    #[ORM\ManyToOne(targetEntity: PluginVulnerability::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PluginVulnerability $matchedWordpressVulnerability = null;

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
        #[ORM\Column(length: 64)]
        private string $version,
        #[ORM\Column]
        private bool $active,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $composerName = null,
        ?PackageType $packageType = null,
    ) {
        $this->packageType = $packageType ?? PackageType::fromCmsType($site->getType());
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

    public function applyUpdate(PackageUpdate $update): void
    {
        $this->outdated = $update->outdated;
        $this->latestVersion = true === $update->outdated ? $update->latestCompatibleVersion : null;
        $this->newerCoreVersion = $update->newerCoreVersion;
        $this->newerCorePackageVersion = $update->newerCorePackageVersion;
    }

    public function getNewerCoreVersion(): ?string
    {
        return $this->newerCoreVersion;
    }

    public function getNewerCorePackageVersion(): ?string
    {
        return $this->newerCorePackageVersion;
    }

    public function getMatchedAdvisory(): ?ExtensionAdvisory
    {
        return $this->matchedAdvisory;
    }

    public function setMatchedAdvisory(?ExtensionAdvisory $advisory): void
    {
        $this->matchedAdvisory = $advisory;
    }

    public function getMatchedWordpressVulnerability(): ?PluginVulnerability
    {
        return $this->matchedWordpressVulnerability;
    }

    public function setMatchedWordpressVulnerability(?PluginVulnerability $vulnerability): void
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
            $this->newerCoreVersion = null;
            $this->newerCorePackageVersion = null;
        }
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
