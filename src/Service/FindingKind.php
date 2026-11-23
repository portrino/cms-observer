<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * Groups the findings of a notification mail, in this order.
 */
enum FindingKind
{
    case Insecure;
    case StoppedReporting;
    case SyncOverdue;

    public function heading(): TranslatableMessage
    {
        return new TranslatableMessage('mail.kind.'.$this->key());
    }

    /** For the mail subject, e.g. "2 new security findings" */
    public function count(int $count): TranslatableMessage
    {
        return new TranslatableMessage('mail.count.'.$this->key(), ['count' => $count]);
    }

    private function key(): string
    {
        return match ($this) {
            self::Insecure => 'insecure',
            self::StoppedReporting => 'stopped_reporting',
            self::SyncOverdue => 'sync_overdue',
        };
    }

    public function isWarning(): bool
    {
        return self::Insecure !== $this;
    }
}
