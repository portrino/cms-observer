<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\CmsType;

final readonly class ProjectListQuery
{
    public function __construct(
        public ?string $q = null,
        public ?CmsType $type = null,
        public ?ProjectSecurityFilter $security = null,
        public ?ProjectSlaFilter $sla = null,
    ) {
    }
}
