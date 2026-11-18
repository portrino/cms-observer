<?php

declare(strict_types=1);

namespace App\Entity;

enum Typo3ReleaseType: string
{
    case Regular = 'regular';
    case Release = 'release';
    case Security = 'security';
    case Development = 'development';
}
