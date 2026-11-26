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

    // length of the title column; TYPO3 falls back to a package's description, which can be longer
    private const int MAX_TITLE_LENGTH = 255;

    /** The title, or the name when the package has none; shortened to what the Observer stores */
    public function displayTitle(): string
    {
        $title = null !== $this->title && '' !== trim($this->title) ? trim($this->title) : $this->name;

        return mb_strlen($title) > self::MAX_TITLE_LENGTH ? mb_substr($title, 0, self::MAX_TITLE_LENGTH - 1).'…' : $title;
    }
}
