<?php

declare(strict_types=1);

namespace App\Dto\Typo3;

/**
 * A version of a TYPO3 package as the TER and Packagist know it together (ExtensionVersionCatalog).
 */
final readonly class ExtensionVersion
{
    /**
     * @param list<int>                                                                  $typo3MajorVersions empty when nothing says
     * @param list<array{sha: string, subject: string, url: string, footer?: bool}>|null $breakingCommits    since the previous
     *                                                                                                       version, from GitHub; null when not checked
     */
    public function __construct(
        public string $version,
        public array $typo3MajorVersions,
        // a TER flag; Packagist-only versions count as not insecure
        public bool $insecure,
        public ?string $uploadComment,
        public ?\DateTimeImmutable $uploadDate,
        public bool $inTer,
        public bool $onPackagist,
        public ?array $breakingCommits = null,
    ) {
    }

    /** Whether something tells about its breaking changes: a TER comment or the checked commits */
    public function hasChangelog(): bool
    {
        return '' !== trim((string) $this->uploadComment) || null !== $this->breakingCommits;
    }

    public function supports(int $major): bool
    {
        return in_array($major, $this->typo3MajorVersions, true);
    }
}
