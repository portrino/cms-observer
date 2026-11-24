<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Repository\SyncStatusRepository;
use App\Service\SyncMonitor;
use App\Tests\Functional\CreatesUserTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class SyncMonitorTest extends KernelTestCase
{
    use CreatesUserTrait;

    public function testListsTheScheduledSyncs(): void
    {
        self::bootKernel();

        $commands = static::getContainer()->get(SyncMonitor::class)->scheduledCommands();

        self::assertSame('17 3 * * *', $commands['app:sync-endoflife-releases'] ?? null);
        self::assertArrayHasKey('app:check-syncs', $commands);
    }

    /**
     * The scheduler worker runs the commands through RunCommandMessage, unlike CommandTester
     * this dispatches the console events the success is recorded on.
     */
    public function testASuccessfulRunThroughTheSchedulerIsRecorded(): void
    {
        self::bootKernel();

        static::getContainer()->get(MessageBusInterface::class)->dispatch(new RunCommandMessage('app:check-stale-sites'));

        $status = static::getContainer()->get(SyncStatusRepository::class)->find('app:check-stale-sites');
        self::assertNotNull($status?->getLastSucceededAt());
        self::assertNotNull($status->getLastStartedAt());
        self::assertTrue($status->isLastRunScheduled());
    }

    public function testAFailedRunIsRecordedWithItsErrorAndKeptAfterASuccess(): void
    {
        self::bootKernel();
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $command = new Command('app:check-syncs');
        $input = new StringInput('app:check-syncs');
        $output = new NullOutput();

        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), ConsoleEvents::COMMAND);
        $dispatcher->dispatch(new ConsoleErrorEvent($input, $output, new \RuntimeException('Could not resolve host: api.wordpress.org'), $command), ConsoleEvents::ERROR);
        $dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, 1), ConsoleEvents::TERMINATE);

        $status = static::getContainer()->get(SyncStatusRepository::class)->find('app:check-syncs');
        self::assertSame('Could not resolve host: api.wordpress.org', $status?->getLastError());
        self::assertNotNull($status->getLastFailedAt());
        self::assertNull($status->getLastSucceededAt());

        // without an exception, e.g. a rate limit the command reports itself
        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), ConsoleEvents::COMMAND);
        $dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, 1), ConsoleEvents::TERMINATE);
        self::assertSame('Failed with exit code 1, see the log.', $status->getLastError());

        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), ConsoleEvents::COMMAND);
        $dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, 0), ConsoleEvents::TERMINATE);
        self::assertNotNull($status->getLastSucceededAt());
        self::assertSame('Failed with exit code 1, see the log.', $status->getLastError());
    }

    public function testAnOverdueSyncIsNotifiedOnceUntilItSucceedsAgain(): void
    {
        self::bootKernel();
        $this->createUser('sync-check@example.test', 'Sync Check', 'password');
        $clock = new MockClock('2026-11-21 12:00:00');
        static::getContainer()->set('clock', $clock);
        $monitor = static::getContainer()->get(SyncMonitor::class);

        // first check after the installation: everything gets tracked, nothing is overdue yet
        $this->runCheck();
        self::assertEmailCount(0);
        self::assertSame(0, $monitor->overdueCount());

        // the daily sync (03:17) succeeds on the 21st, then misses the runs of the 22nd and 23rd
        $monitor->recordSuccess('app:sync-endoflife-releases');
        $clock->modify('2026-11-23 12:00:00');
        self::assertFalse($this->stateOf($monitor, 'app:sync-endoflife-releases'));

        // and the 24th: three missed runs
        $clock->modify('2026-11-24 12:00:00');
        self::assertTrue($this->stateOf($monitor, 'app:sync-endoflife-releases'));

        $monitor->recordFailure('app:sync-endoflife-releases', 'Could not resolve host: endoflife.date');
        $this->runCheck();
        self::assertEmailCount(1);
        self::assertEmailHtmlBodyContains(self::getMailerMessage() ?? self::fail('No email sent'), 'Sync app:sync-endoflife-releases');
        self::assertEmailHtmlBodyContains(self::getMailerMessage() ?? self::fail('No email sent'), 'Could not resolve host: endoflife.date');

        // still overdue an hour later, already notified
        $clock->modify('+1 hour');
        $this->runCheck();
        self::assertEmailCount(1);

        // succeeds again, then fails again for three runs: notified again
        $monitor->recordSuccess('app:sync-endoflife-releases');
        self::assertFalse($this->stateOf($monitor, 'app:sync-endoflife-releases'));
        $clock->modify('+4 days');
        $this->runCheck();
        self::assertEmailCount(2);
    }

    private function runCheck(): void
    {
        new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:check-syncs'))->execute([]);
    }

    private function stateOf(SyncMonitor $monitor, string $command): ?bool
    {
        foreach ($monitor->states() as $state) {
            if ($state->command === $command) {
                return $state->overdue;
            }
        }

        return null;
    }
}
