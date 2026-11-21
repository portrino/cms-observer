<?php

declare(strict_types=1);

namespace App\Service;

final readonly class SecurityFinding
{
    public function __construct(
        public FindingKind $kind,
        public string $name,
        public string $url,
        public string $subject,
        public string $detail,
    ) {
    }
}
