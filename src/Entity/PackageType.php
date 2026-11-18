<?php

declare(strict_types=1);

namespace App\Entity;

enum PackageType: string
{
    case Typo3CmsExtension = 'typo3-cms-extension';
    case WordpressPlugin = 'wordpress-plugin';

    public static function fromCmsType(CmsType $cmsType): self
    {
        return match ($cmsType) {
            CmsType::Typo3 => self::Typo3CmsExtension,
            CmsType::WordPress => self::WordpressPlugin,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Typo3CmsExtension => 'TYPO3 Extension',
            self::WordpressPlugin => 'WordPress Plugin',
        };
    }
}
