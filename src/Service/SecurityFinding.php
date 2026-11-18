<?php

declare(strict_types=1);

namespace App\Service;

final readonly class SecurityFinding
{
    public function __construct(
        public int $siteId,
        public string $siteName,
        public string $subject,
        public string $detail,
    ) {
    }
}
