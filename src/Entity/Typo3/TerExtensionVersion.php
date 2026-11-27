<?php

declare(strict_types=1);

namespace App\Entity\Typo3;

use App\Repository\Typo3\TerExtensionVersionRepository;
use App\Service\StringList;
use Doctrine\ORM\Mapping as ORM;

/**
 * Caches one TER-published (extension key, version) pair's metadata, fetched
 * in bulk per extension key via TerExtensionApiClient::getVersionHistory().
 * Backs both the insecure/outdated cross-reference (SitePackageTerEvaluator)
 * and the "newer versions" table shown on the site detail page.
 */
#[ORM\Entity(repositoryClass: TerExtensionVersionRepository::class)]
#[ORM\Table(name: 'typo3_ter_extension_version')]
#[ORM\UniqueConstraint(name: 'ter_extension_version', columns: ['extension_key', 'version'])]
class TerExtensionVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $fetchedAt;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $extensionKey,
        #[ORM\Column(length: 32)]
        private string $version,
        #[ORM\Column(length: 255)]
        private string $title,
        #[ORM\Column]
        private bool $insecure,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $uploadComment,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $state,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $typo3Versions,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $uploadDate,
    ) {
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function update(
        string $title,
        bool $insecure,
        ?string $description,
        ?string $uploadComment,
        ?string $state,
        ?string $typo3Versions,
        ?\DateTimeImmutable $uploadDate,
    ): void {
        $this->title = $title;
        $this->description = $description;
        $this->uploadComment = $uploadComment;
        $this->state = $state;
        $this->typo3Versions = $typo3Versions;
        $this->uploadDate = $uploadDate;
        $this->insecure = $insecure;
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExtensionKey(): string
    {
        return $this->extensionKey;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUploadComment(): ?string
    {
        return $this->uploadComment;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getTypo3Versions(): ?string
    {
        return $this->typo3Versions;
    }

    /**
     * Parses the stored "12, 13" string back into [12, 13].
     *
     * @return list<int>
     */
    public function getTypo3MajorVersions(): array
    {
        return array_map(intval(...), StringList::trimExplode(',', $this->typo3Versions));
    }

    public function getUploadDate(): ?\DateTimeImmutable
    {
        return $this->uploadDate;
    }

    public function isInsecure(): bool
    {
        return $this->insecure;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }
}
