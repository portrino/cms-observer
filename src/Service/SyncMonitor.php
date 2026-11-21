<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SyncStatus;
use App\Repository\SyncStatusRepository;
use Cron\CronExpression;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;

/**
 * Tracks the #[AsCronTask] commands of the scheduler: when each last succeeded and whether it
 * is overdue, i.e. missed its last three scheduled runs. A source that is down, a changed API
 * or a stopped worker would otherwise leave every rating silently outdated.
 */
class SyncMonitor
{
    private const int MISSED_RUNS = 3;

    public function __construct(
        // App\Schedule is only the inner service, the #[AsCronTask] tasks are added to this one
        #[Autowire(service: 'scheduler.provider.default')]
        private readonly ScheduleProviderInterface $scheduleProvider,
        private readonly SyncStatusRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        // the lazy commands carry their #[AsCommand] description without being instantiated
        #[Autowire(service: 'console.command_loader')]
        private readonly CommandLoaderInterface $commandLoader,
    ) {
    }

    /**
     * @return array<string, string> cron expression by command name
     */
    public function scheduledCommands(): array
    {
        $now = $this->clock->now();
        $commands = [];

        foreach ($this->scheduleProvider->getSchedule()->getRecurringMessages() as $recurringMessage) {
            $trigger = $recurringMessage->getTrigger();
            if (!$trigger instanceof CronExpressionTrigger) {
                continue;
            }

            $context = new MessageContext('default', $recurringMessage->getId(), $trigger, $now);
            foreach ($recurringMessage->getProvider()->getMessages($context) as $message) {
                if ($message instanceof RunCommandMessage) {
                    $commands[explode(' ', $message->input)[0]] = (string) $trigger;
                }
            }
        }

        ksort($commands);

        return $commands;
    }

    /**
     * @return list<SyncState>
     */
    public function states(): array
    {
        $statuses = [];
        foreach ($this->repository->findAll() as $status) {
            $statuses[$status->getCommand()] = $status;
        }

        $states = [];
        foreach ($this->scheduledCommands() as $command => $expression) {
            $status = $statuses[$command] ?? null;
            $description = $this->commandLoader->has($command) ? $this->commandLoader->get($command)->getDescription() : '';
            $states[] = new SyncState($command, $description, $expression, $status?->getLastSucceededAt(), $this->isOverdue($expression, $status));
        }

        return $states;
    }

    public function overdueCount(): int
    {
        return count(array_filter($this->states(), static fn (SyncState $state): bool => $state->overdue));
    }

    public function recordSuccess(string $command): void
    {
        if (!array_key_exists($command, $this->scheduledCommands())) {
            return;
        }

        $this->statusOf($command)->recordSuccess($this->clock->now());
        $this->entityManager->flush();
    }

    /**
     * Overdue syncs not notified yet, marked as notified. Starts tracking syncs without a status,
     * so one that never succeeds gets overdue too. The caller flushes.
     *
     * @return list<SyncStatus>
     */
    public function takeNewlyOverdue(): array
    {
        $overdue = [];
        foreach ($this->scheduledCommands() as $command => $expression) {
            $status = $this->statusOf($command);
            if ($this->isOverdue($expression, $status) && !$status->isOverdueNotified()) {
                $status->markOverdueNotified($this->clock->now());
                $overdue[] = $status;
            }
        }

        return $overdue;
    }

    private function isOverdue(string $expression, ?SyncStatus $status): bool
    {
        if (null === $status) {
            return false; // not tracked yet, e.g. right after the installation
        }

        $deadline = new CronExpression($expression)->getPreviousRunDate($this->clock->now(), self::MISSED_RUNS - 1);

        return $status->getLastSucceededOrTrackedSince() < $deadline;
    }

    private function statusOf(string $command): SyncStatus
    {
        $status = $this->repository->find($command);
        if (null === $status) {
            $status = new SyncStatus($command, $this->clock->now());
            $this->entityManager->persist($status);
        }

        return $status;
    }
}
