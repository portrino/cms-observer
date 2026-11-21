<?php

declare(strict_types=1);

namespace App\Entity;

enum PackageType: string
{
    case Typo3CmsExtension = 'typo3-cms-extension';
    case WordpressPlugin = 'wordpress-plugin';
    case WordpressTheme = 'wordpress-theme';

    public static function fromCmsType(CmsType $cmsType): self
    {
        return match ($cmsType) {
            CmsType::Typo3 => self::Typo3CmsExtension,
            CmsType::WordPress => self::WordpressPlugin,
        };
    }

    /**
     * Every type a CMS has: a "packages" list from that CMS is complete for each of them.
     *
     * @return list<self>
     */
    public static function allOf(CmsType $cmsType): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type): bool => null !== self::fromReport($cmsType, $type->reportType())));
    }

    /**
     * The "type" of a reported package; without one it's the CMS's default type.
     *
     * @return self|null null: not a type of this CMS, e.g. from a newer client
     */
    public static function fromReport(CmsType $cmsType, ?string $reportType): ?self
    {
        return match ([$cmsType, $reportType ?? self::fromCmsType($cmsType)->reportType()]) {
            [CmsType::Typo3, 'extension'] => self::Typo3CmsExtension,
            [CmsType::WordPress, 'plugin'] => self::WordpressPlugin,
            [CmsType::WordPress, 'theme'] => self::WordpressTheme,
            default => null,
        };
    }

    /** The "type" value of the report API */
    public function reportType(): string
    {
        return match ($this) {
            self::Typo3CmsExtension => 'extension',
            self::WordpressPlugin => 'plugin',
            self::WordpressTheme => 'theme',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Typo3CmsExtension => 'TYPO3 Extension',
            self::WordpressPlugin => 'WordPress Plugin',
            self::WordpressTheme => 'WordPress Theme',
        };
    }
}
