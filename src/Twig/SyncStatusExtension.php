<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\SyncMonitor;
use Twig\Attribute\AsTwigFunction;

final readonly class SyncStatusExtension
{
    public function __construct(private SyncMonitor $syncMonitor)
    {
    }

    /** For the sidebar badge on every page */
    #[AsTwigFunction(name: 'overdue_sync_count')]
    public function overdueSyncCount(): int
    {
        return $this->syncMonitor->overdueCount();
    }
}
