<?php

declare(strict_types=1);

namespace App\Dto\Typo3;

/**
 * A version of a TYPO3 package as the TER and Packagist know it together (ExtensionVersionCatalog).
 */
final readonly class ExtensionVersion
{
    /**
     * @param list<int> $typo3MajorVersions empty when nothing says
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
    ) {
    }

    public function supports(int $major): bool
    {
        return in_array($major, $this->typo3MajorVersions, true);
    }
}
