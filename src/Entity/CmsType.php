<?php

declare(strict_types=1);

namespace App\Entity;

enum CmsType: string
{
    case Typo3 = 'typo3';
    case WordPress = 'wordpress';

    public function label(): string
    {
        return match ($this) {
            self::Typo3 => 'TYPO3',
            self::WordPress => 'WordPress',
        };
    }

    /** The asset path of the CMS logo */
    public function icon(): string
    {
        return match ($this) {
            self::Typo3 => 'images/typo3-icon.svg',
            self::WordPress => 'images/wordpress-icon.svg',
        };
    }

    /** Translation key of what the CMS calls its packages */
    public function packagesLabel(): string
    {
        return match ($this) {
            self::Typo3 => 'package.label.extensions',
            self::WordPress => 'package.label.plugins_themes',
        };
    }

    public function releaseNotesUrl(string $version): string
    {
        return match ($this) {
            self::Typo3 => 'https://get.typo3.org/release-notes/'.$version,
            self::WordPress => 'https://wordpress.org/documentation/wordpress-version/version-'.str_replace('.', '-', $version).'/',
        };
    }
}
