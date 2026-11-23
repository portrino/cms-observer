<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Translation\TranslatableMessage;

final readonly class SecurityFinding
{
    public function __construct(
        public FindingKind $kind,
        public string $name,
        public string $url,
        public TranslatableMessage|string $subject,
        public TranslatableMessage|string $detail,
    ) {
    }
}
