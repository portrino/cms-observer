<?php

declare(strict_types=1);

namespace App\Entity\Wordpress;

use App\Repository\Wordpress\PluginInfoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Caches one WordPress plugin slug's info from
 * api.wordpress.org/plugins/info/1.2/ (Wordpress\PluginSynchronizer). One row
 * per slug - unlike TER, this API only exposes rich detail for the latest
 * published version (no per-version compatibility/security history), so
 * there's no per-version equivalent of Typo3\TerExtensionVersion here. It also
 * has no security/insecure signal at all - see Wordpress\SitePackageEvaluator
 * for why that stays unknown for WordPress plugins.
 */
#[ORM\Entity(repositoryClass: PluginInfoRepository::class)]
#[ORM\Table(name: 'wordpress_plugin_info')]
class PluginInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(length: 255, unique: true)]
        private string $slug,
        #[ORM\Column(length: 32)]
        private string $latestVersion,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $requiresWpVersion,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $testedUpToWpVersion,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description,
        /**
         * The plugin's bootstrap file name within its SVN tag folders, e.g.
         * "akismet.php" - not always "{slug}.php" (contact-form-7's is
         * wp-contact-form-7.php). Resolved once via
         * Wordpress\ApiClient::findBootstrapFilename() and cached here since it's
         * stable across a plugin's versions, so per-version compatibility
         * lookups (WordpressPluginVersionSynchronizer... see Wordpress\PluginSynchronizer)
         * don't need to rediscover it each time.
         */
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $bootstrapFilename,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(
        string $latestVersion,
        ?string $requiresWpVersion,
        ?string $testedUpToWpVersion,
        ?string $description,
        ?string $bootstrapFilename,
    ): void {
        $this->latestVersion = $latestVersion;
        $this->requiresWpVersion = $requiresWpVersion;
        $this->testedUpToWpVersion = $testedUpToWpVersion;
        $this->description = $description;
        // A plugin renaming its own bootstrap file is rare but not impossible -
        // only overwrite the cached name if this sync actually resolved one.
        if (null !== $bootstrapFilename) {
            $this->bootstrapFilename = $bootstrapFilename;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLatestVersion(): string
    {
        return $this->latestVersion;
    }

    public function getRequiresWpVersion(): ?string
    {
        return $this->requiresWpVersion;
    }

    public function getTestedUpToWpVersion(): ?string
    {
        return $this->testedUpToWpVersion;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getBootstrapFilename(): ?string
    {
        return $this->bootstrapFilename;
    }

    /**
     * Whether $coreVersion falls within this plugin's declared
     * requires..tested WordPress compatibility range. Null (unknown) if
     * either bound wasn't declared - never assume compatibility.
     */
    public function isCompatibleWith(string $coreVersion): ?bool
    {
        if (null === $this->requiresWpVersion || null === $this->testedUpToWpVersion) {
            return null;
        }

        return version_compare($coreVersion, $this->requiresWpVersion, '>=')
            && version_compare($coreVersion, $this->testedUpToWpVersion, '<=');
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
