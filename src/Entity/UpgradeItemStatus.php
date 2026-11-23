<?php

declare(strict_types=1);

namespace App\Entity;

enum UpgradeItemStatus: string
{
    /** the installed version already supports the target core */
    case Compatible = 'compatible';
    /** a newer version supports both the current and the target core: update before the core */
    case UpdateFirst = 'update-first';
    /** only a version for the target core exists: update together with the core */
    case Update = 'update';
    /** no version supports the target core */
    case Blocker = 'blocker';
    /** set by hand: the package gets replaced by another one, e.g. without a compatible version */
    case Replace = 'replace';
    /** set by hand: the package is no longer needed */
    case Remove = 'remove';
    /** no data to judge, e.g. a package not in the CMS's registry or a dev branch */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Compatible => 'Compatible',
            self::UpdateFirst => 'Update before core',
            self::Update => 'Update with core',
            self::Blocker => 'Show-stopper',
            self::Replace => 'Replace',
            self::Remove => 'Remove',
            self::Manual => 'Check manually',
        };
    }

    /** Bootstrap color of the badge */
    public function color(): string
    {
        return match ($this) {
            self::Compatible => 'success',
            self::UpdateFirst => 'info',
            self::Update => 'primary',
            self::Blocker => 'danger',
            self::Replace => 'secondary',
            self::Remove => 'neutral',
            self::Manual => 'warning',
        };
    }
}
