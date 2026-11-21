<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SiteReportRequest
{
    /**
     * @param PackageReport[]|null $extensions
     * @param PackageReport[]|null $packages
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $coreVersion,
        #[Assert\NotBlank]
        public string $phpVersion,
        #[Assert\NotBlank]
        public string $dbVersion,
        #[Assert\Valid]
        // Older clients, kept for good: the packages of the CMS's default type (TYPO3 extensions,
        // WordPress plugins); null: not reported (left as they are), []: none installed
        public ?array $extensions = null,
        // Optional, so reports from older clients without these fields are still accepted
        public ?string $clientVersion = null,
        public ?string $applicationContext = null,
        /** @var list<string>|null hosts of the reporting instance; null or empty means "unknown, don't check" */
        #[Assert\All([new Assert\Type('string')])]
        public ?array $domains = null,
        // endoflife.date slug (mariadb, mysql, postgresql), not validated: unknown values must not reject a report
        public ?string $dbPlatform = null,
        // Replaces "extensions", which older clients still send: every package of every type, each
        // with its "type"; null leaves the stored packages as they are, [] means none are installed
        #[Assert\Valid]
        public ?array $packages = null,
    ) {
    }
}
