<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Groups the findings of a notification mail, in this order.
 */
enum FindingKind
{
    case Insecure;
    case StoppedReporting;
    case SyncOverdue;

    public function heading(): string
    {
        return match ($this) {
            self::Insecure => 'Insecure',
            self::StoppedReporting => 'Stopped reporting',
            self::SyncOverdue => 'Syncs overdue',
        };
    }

    /** For the mail subject, e.g. "2 new security findings" */
    public function count(int $count): string
    {
        $plural = 1 === $count ? '' : 's';

        return match ($this) {
            self::Insecure => sprintf('%d new security finding%s', $count, $plural),
            self::StoppedReporting => sprintf('%d project%s stopped reporting', $count, $plural),
            self::SyncOverdue => sprintf('%d sync%s overdue', $count, $plural),
        };
    }

    public function isWarning(): bool
    {
        return self::Insecure !== $this;
    }
}
