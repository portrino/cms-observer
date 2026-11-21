<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\SyncMonitor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Records every successful run of a scheduled sync, whether the scheduler worker or someone
 * on the command line ran it.
 */
#[AsEventListener]
final readonly class RecordSyncSuccessListener
{
    public function __construct(private SyncMonitor $syncMonitor)
    {
    }

    public function __invoke(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand()?->getName();
        if (Command::SUCCESS === $event->getExitCode() && null !== $command) {
            $this->syncMonitor->recordSuccess($command);
        }
    }
}
