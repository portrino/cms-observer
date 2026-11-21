<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\SyncMonitor;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Records every run of a scheduled sync, whether the scheduler worker or someone on the
 * command line ran it: a success with its start and end, a failure with its error.
 */
#[AsEventListener(event: ConsoleCommandEvent::class, method: 'onStart')]
#[AsEventListener(event: ConsoleErrorEvent::class, method: 'onError')]
#[AsEventListener(event: ConsoleTerminateEvent::class, method: 'onTerminate')]
final class RecordSyncSuccessListener
{
    /** @var array<string, \DateTimeImmutable> start by command name; the worker runs one command after another */
    private array $startedAt = [];

    /** @var array<string, string> the exception's message by command name */
    private array $errors = [];

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

    public function onError(ConsoleErrorEvent $event): void
    {
        $command = $event->getCommand()?->getName();
        if (null !== $command) {
            $this->errors[$command] = $event->getError()->getMessage();
        }
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand()?->getName();
        if (null === $command) {
            return;
        }

        $startedAt = $this->startedAt[$command] ?? null;
        $error = $this->errors[$command] ?? null;
        unset($this->startedAt[$command], $this->errors[$command]);

        if (Command::SUCCESS === $event->getExitCode()) {
            // the worker passes RunCommandMessage's input as a string, the command line its argv
            $this->syncMonitor->recordSuccess($command, $startedAt, $event->getInput() instanceof StringInput);
        } else {
            // a command that fails without an exception (e.g. a rate limit) only says so in its output
            $this->syncMonitor->recordFailure($command, $error ?? sprintf('Failed with exit code %d, see the log.', $event->getExitCode()));
        }
    }
}
