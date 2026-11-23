<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Color of a calculation row's notes, with the meaning of the agency's Excel legend.
 */
enum UpgradeNoteLevel: string
{
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Compatible or simple update',
            self::Warning => 'Migration or manual changes needed',
            self::Danger => 'No compatible version yet',
        };
    }

    /** The color a new row's notes start with, as far as the status tells it */
    public static function forStatus(?UpgradeItemStatus $status): ?self
    {
        return match ($status) {
            UpgradeItemStatus::Compatible => self::Success,
            UpgradeItemStatus::Blocker => self::Danger,
            default => null,
        };
    }
}
