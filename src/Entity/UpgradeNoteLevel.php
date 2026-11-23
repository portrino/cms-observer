<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Color of a calculation row's notes, with the meaning of the agency's Excel legend.
 */
enum UpgradeNoteLevel: string implements TranslatableInterface
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

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('calculation.note_level.'.$this->value, locale: $locale);
    }
}
