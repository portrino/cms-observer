<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\SyncMonitor;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Records every successful run of a scheduled sync with its start and end, whether the
 * scheduler worker or someone on the command line ran it.
 */
#[AsEventListener(event: ConsoleCommandEvent::class, method: 'onStart')]
#[AsEventListener(event: ConsoleTerminateEvent::class, method: 'onTerminate')]
final class RecordSyncSuccessListener
{
    /** @var array<string, \DateTimeImmutable> start by command name; the worker runs one command after another */
    private array $startedAt = [];

    public function __construct(
        private readonly SyncMonitor $syncMonitor,
        private readonly ClockInterface $clock,
    ) {
    }

    public function onStart(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand()?->getName();
        if (null !== $command) {
            $this->startedAt[$command] = $this->clock->now();
        }
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand()?->getName();
        if (null === $command) {
            return;
        }

        $startedAt = $this->startedAt[$command] ?? null;
        unset($this->startedAt[$command]);

        if (Command::SUCCESS === $event->getExitCode()) {
            // the worker passes RunCommandMessage's input as a string, the command line its argv
            $this->syncMonitor->recordSuccess($command, $startedAt, $event->getInput() instanceof StringInput);
        }
    }
}
