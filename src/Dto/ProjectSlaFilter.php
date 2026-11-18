<?php

declare(strict_types=1);

namespace App\Dto;

enum ProjectSlaFilter: string
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
}
