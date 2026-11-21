<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PackageReport
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        #[Assert\NotBlank]
        public string $title,
        #[Assert\NotBlank]
        public string $version,
        public bool $active = true,
        public ?string $composerName = null,
        // "extension", "plugin" or "theme", see PackageType::fromReport(); not validated, a newer type mustn't reject the report
        public ?string $type = null,
    ) {
    }
}
