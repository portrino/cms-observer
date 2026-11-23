<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Site;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ProjectSecurityFilter: string implements TranslatableInterface
{
    case Insecure = 'insecure';
    case Infrastructure = 'infrastructure';
    case NoKnownIssues = 'ok';

    public function label(): string
    {
        return match ($this) {
            self::Insecure => 'Insecure core or packages',
            self::Infrastructure => 'PHP/database warnings',
            self::NoKnownIssues => 'No known issues',
        };
    }

    public function matches(Site $site, ?bool $packagesInsecure): bool
    {
        $insecure = true === $site->isCoreInsecure() || true === $packagesInsecure;

        return match ($this) {
            self::Insecure => $insecure,
            self::Infrastructure => $site->hasInfrastructureWarning(),
            self::NoKnownIssues => !$insecure && !$site->hasInfrastructureWarning(),
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('project.filter.'.$this->value, locale: $locale);
    }
}
