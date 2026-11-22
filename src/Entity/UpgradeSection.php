<?php

declare(strict_types=1);

namespace App\Entity;

enum UpgradeSection: string
{
    case Positions = 'positions';
    case Packages = 'packages';
    case OwnPackages = 'own-packages';
    case Optional = 'optional';

    public function label(): string
    {
        return match ($this) {
            self::Positions => 'Positions',
            self::Packages => 'Third-party packages',
            self::OwnPackages => 'Custom packages',
            self::Optional => 'Optional',
        };
    }

    /** Rows of this section describe a package with versions and a status */
    public function hasPackages(): bool
    {
        return self::Packages === $this || self::OwnPackages === $this;
    }
}
