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
}
