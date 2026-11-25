<?php

declare(strict_types=1);

namespace App\Entity\Typo3;

enum ReleaseType: string
{
    case Regular = 'regular';
    case Release = 'release';
    case Security = 'security';
    case Development = 'development';
}
