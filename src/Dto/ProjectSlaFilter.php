<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ProjectSlaFilter: string implements TranslatableInterface
{
    case With = 'with';
    case Without = 'without';

    public function label(): string
    {
        return match ($this) {
            self::With => 'With SLA',
            self::Without => 'Without SLA',
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('project.filter.'.$this->value.'_sla', locale: $locale);
    }
}
