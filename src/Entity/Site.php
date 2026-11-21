<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
class Site implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $currentCoreVersion = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $currentPhpVersion = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $currentDbVersion = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $currentDbPlatform = null;

    #[ORM\Column(nullable: true)]
    private ?bool $coreInsecure = null;

    #[ORM\Column(nullable: true)]
    private ?bool $coreOutdated = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $coreLatestVersion = null;

    #[ORM\Column(nullable: true)]
    private ?bool $phpInsecure = null;

    #[ORM\Column(nullable: true)]
    private ?bool $phpOutdated = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phpLatestVersion = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phpEolUrl = null;

    #[ORM\Column(nullable: true)]
    private ?bool $dbInsecure = null;

    #[ORM\Column(nullable: true)]
    private ?bool $dbOutdated = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $dbLatestVersion = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dbEolUrl = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastRejectedReportAt = null;

    /** TYPO3: e.g. "Production/Live", WordPress: wp_get_environment_type(), e.g. "production" */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $applicationContext = null;

    /** @var list<string>|null */
    #[ORM\Column(nullable: true)]
    private ?array $lastRejectedReportDomains = null;

    /** No report for this long and the site counts as stale (badge, notification) */
    public const string STALE_AFTER = '-2 days';

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $name,
        #[ORM\Column(enumType: CmsType::class)]
        private CmsType $type,
        #[ORM\Column(length: 255)]
        private string $url,
        #[ORM\Column(length: 64, unique: true)]
        private string $tokenHash,
        #[ORM\Column]
        private bool $slaActive = false,
        #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'sites')]
        #[ORM\JoinColumn(nullable: true)]
        private ?Organization $organization = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $repositoryUrl = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $issueTrackerUrl = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): CmsType
    {
        return $this->type;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function replaceTokenHash(string $tokenHash): void
    {
        $this->tokenHash = $tokenHash;
    }

    public function isStale(): bool
    {
        return null !== $this->lastSeenAt && $this->lastSeenAt < new \DateTimeImmutable(self::STALE_AFTER);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getCurrentCoreVersion(): ?string
    {
        return $this->currentCoreVersion;
    }

    public function getCurrentPhpVersion(): ?string
    {
        return $this->currentPhpVersion;
    }

    public function getCurrentDbVersion(): ?string
    {
        return $this->currentDbVersion;
    }

    public function getCurrentDbPlatform(): ?string
    {
        return $this->currentDbPlatform;
    }

    public function recordReport(string $coreVersion, string $phpVersion, string $dbVersion, ?string $dbPlatform = null): void
    {
        $this->currentCoreVersion = $coreVersion;
        $this->currentPhpVersion = $phpVersion;
        $this->currentDbVersion = $dbVersion;
        $this->currentDbPlatform = $dbPlatform;
        $this->lastSeenAt = new \DateTimeImmutable();
    }

    /**
     * Whether a report's domains include this site's host. Staging copies that push with the
     * production token usually have their own domain and are rejected this way.
     *
     * @param list<string> $domains
     */
    public function matchesAnyDomain(array $domains): bool
    {
        $host = strtolower((string) parse_url($this->url, PHP_URL_HOST));

        return in_array($host, array_map(strtolower(...), $domains), true);
    }

    /**
     * @param list<string> $domains
     */
    public function recordRejectedReport(array $domains): void
    {
        $this->lastRejectedReportAt = new \DateTimeImmutable();
        $this->lastRejectedReportDomains = $domains;
    }

    public function getLastRejectedReportAt(): ?\DateTimeImmutable
    {
        return $this->lastRejectedReportAt;
    }

    /**
     * @return list<string>|null
     */
    public function getLastRejectedReportDomains(): ?array
    {
        return $this->lastRejectedReportDomains;
    }

    /**
     * PHP or database insecure or outdated. Kept apart from the core/package security status:
     * on shared hosting the site's maintainers often can't change these versions themselves.
     */
    public function hasInfrastructureWarning(): bool
    {
        return true === $this->phpInsecure || true === $this->phpOutdated || true === $this->dbInsecure || true === $this->dbOutdated
            || $this->runsOutsideProductionContext();
    }

    public function getApplicationContext(): ?string
    {
        return $this->applicationContext;
    }

    public function recordApplicationContext(?string $applicationContext): void
    {
        $this->applicationContext = $applicationContext;
    }

    /**
     * Reports only come from the production URL (see matchesAnyDomain()), where a Development or
     * Testing context shows error details and debug output to the public.
     */
    public function runsOutsideProductionContext(): bool
    {
        // case-insensitive: WordPress reports its environment type in lowercase
        return null !== $this->applicationContext && !str_starts_with(strtolower($this->applicationContext), 'production');
    }

    public function isCoreInsecure(): ?bool
    {
        return $this->coreInsecure;
    }

    public function isCoreOutdated(): ?bool
    {
        return $this->coreOutdated;
    }

    public function getCoreLatestVersion(): ?string
    {
        return $this->coreLatestVersion;
    }

    public function setCoreStatus(?bool $insecure, ?bool $outdated, ?string $latestVersion): void
    {
        $this->coreInsecure = $insecure;
        $this->coreOutdated = $outdated;
        $this->coreLatestVersion = $latestVersion;
    }

    public function isSlaActive(): bool
    {
        return $this->slaActive;
    }

    public function setSlaActive(bool $slaActive): void
    {
        $this->slaActive = $slaActive;
    }

    public function updateDetails(
        string $name,
        CmsType $type,
        string $url,
        bool $slaActive,
        ?Organization $organization,
        ?string $repositoryUrl,
        ?string $issueTrackerUrl,
    ): void {
        $this->name = $name;
        $this->type = $type;
        $this->url = $url;
        $this->slaActive = $slaActive;
        $this->organization = $organization;
        $this->repositoryUrl = $repositoryUrl;
        $this->issueTrackerUrl = $issueTrackerUrl;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function getRepositoryUrl(): ?string
    {
        return $this->repositoryUrl;
    }

    public function getIssueTrackerUrl(): ?string
    {
        return $this->issueTrackerUrl;
    }

    public function isPhpInsecure(): ?bool
    {
        return $this->phpInsecure;
    }

    public function isPhpOutdated(): ?bool
    {
        return $this->phpOutdated;
    }

    public function getPhpLatestVersion(): ?string
    {
        return $this->phpLatestVersion;
    }

    public function getPhpEolUrl(): ?string
    {
        return $this->phpEolUrl;
    }

    public function setPhpStatus(?bool $insecure, ?bool $outdated, ?string $latestVersion, ?string $eolUrl): void
    {
        $this->phpInsecure = $insecure;
        $this->phpOutdated = $outdated;
        $this->phpLatestVersion = $latestVersion;
        $this->phpEolUrl = $eolUrl;
    }

    public function isDbInsecure(): ?bool
    {
        return $this->dbInsecure;
    }

    public function isDbOutdated(): ?bool
    {
        return $this->dbOutdated;
    }

    public function getDbLatestVersion(): ?string
    {
        return $this->dbLatestVersion;
    }

    public function getDbEolUrl(): ?string
    {
        return $this->dbEolUrl;
    }

    public function setDbStatus(?bool $insecure, ?bool $outdated, ?string $latestVersion, ?string $eolUrl): void
    {
        $this->dbInsecure = $insecure;
        $this->dbOutdated = $outdated;
        $this->dbLatestVersion = $latestVersion;
        $this->dbEolUrl = $eolUrl;
    }

    public function getRoles(): array
    {
        return ['ROLE_SITE'];
    }

    public function getUserIdentifier(): string
    {
        if (null === $this->id) {
            throw new \LogicException('Site has no id yet.');
        }

        return (string) $this->id;
    }
}
