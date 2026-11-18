<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SiteReportRequest
{
    /**
     * @param ExtensionReport[]|null $extensions
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $coreVersion,
        #[Assert\NotBlank]
        public string $phpVersion,
        #[Assert\NotBlank]
        public string $dbVersion,
        #[Assert\Valid]
        // null: not reported (packages are left as they are), []: no packages installed
        public ?array $extensions = null,
        // Optional, so reports from older clients without these fields are still accepted
        public ?string $clientVersion = null,
        public ?string $applicationContext = null,
        /** @var list<string>|null hosts of the reporting instance; null or empty means "unknown, don't check" */
        #[Assert\All([new Assert\Type('string')])]
        public ?array $domains = null,
        // endoflife.date slug (mariadb, mysql, postgresql), not validated: unknown values must not reject a report
        public ?string $dbPlatform = null,
    ) {
    }
}
