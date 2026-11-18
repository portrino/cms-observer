<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\SiteEventType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SiteHistoryQuery
{
    public function __construct(
        public ?string $q = null,
        public ?SiteEventType $type = null,
        #[Assert\Positive]
        public int $page = 1,
    ) {
    }
}
