<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PackageReport
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        // display only: an empty or missing title (e.g. a site package without one) mustn't reject the report
        public ?string $title,
        #[Assert\NotBlank]
        public string $version,
        public bool $active = true,
        public ?string $composerName = null,
        // "extension", "plugin" or "theme", see PackageType::fromReport(); not validated, a newer type mustn't reject the report
        public ?string $type = null,
    ) {
    }

    /** The title, or the name when the package has none */
    public function displayTitle(): string
    {
        return null !== $this->title && '' !== trim($this->title) ? $this->title : $this->name;
    }
}
