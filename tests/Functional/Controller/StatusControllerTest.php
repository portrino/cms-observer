<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\SyncStatus;
use App\Service\SyncMonitor;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StatusControllerTest extends WebTestCase
{
    use CreatesUserTrait;

    public function testShowsEachSyncAndBadgesTheSidebarWhenOneIsOverdue(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('status@example.test', 'Status', 'password'));

        $client->request('GET', '/status');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'app:sync-endoflife-releases');
        self::assertSelectorExists('tr:has(code:contains("app:sync-endoflife-releases")) [data-bs-toggle="tooltip"][title*="endoflife.date"]');
        self::assertSelectorNotExists('.sidebar-badge');

        // tracked for a week, never succeeded
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new SyncStatus('app:sync-endoflife-releases', new \DateTimeImmutable('-7 days')));
        $entityManager->flush();

        $client->request('GET', '/');
        self::assertSelectorTextSame('.sidebar-badge', '1');

        static::getContainer()->get(SyncMonitor::class)->recordFailure('app:sync-endoflife-releases', 'Could not resolve host: endoflife.date');

        $client->request('GET', '/status');
        self::assertSelectorTextContains('tr:has(code:contains("app:sync-endoflife-releases")) .badge', 'Overdue');
        self::assertSelectorExists('tr:has(code:contains("app:sync-endoflife-releases")) [title="Could not resolve host: endoflife.date"]');
    }

    public function testTellsTheTimezoneAndWhenTheSchedulerLastRan(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('status-tz@example.test', 'Status Tz', 'password'));
        $timezone = date_default_timezone_get();

        $client->request('GET', '/status');
        self::assertSelectorTextContains('.alert-warning', "hasn't run a sync yet");

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $status = new SyncStatus('app:check-syncs', new \DateTimeImmutable('-1 day'));
        $status->recordSuccess(new \DateTimeImmutable('2026-11-21 14:15:00'), new \DateTimeImmutable('2026-11-21 14:14:00'), true);
        $entityManager->persist($status);
        $entityManager->flush();

        $client->request('GET', '/status');
        self::assertSelectorTextContains('.alert-info', 'Current server time is');
        self::assertSelectorTextContains('.alert-info', '('.$timezone.', GMT');
        self::assertSelectorTextContains('.alert-info', 'The last sync was app:check-syncs, started (automatically) on 2026-11-21 at 14:14 and ended on 2026-11-21 at 14:15.');

        // a worker with another timezone, e.g. an unset date.timezone in the CLI's php.ini;
        // the client rebooted the kernel, which cleared the entity manager
        $otherTimezone = 'UTC' === $timezone ? 'Europe/Berlin' : 'UTC';
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->find(SyncStatus::class, 'app:check-syncs')?->recordSuccess(new \DateTimeImmutable('2026-11-21 13:30:00', new \DateTimeZone($otherTimezone)));
        $entityManager->flush();

        $client->request('GET', '/status');
        self::assertSelectorTextContains('.alert-warning', "the scheduler's worker runs in {$otherTimezone}");
    }

    public function testSendsATestMail(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('mail-test@example.test', 'Mail Test', 'password'));

        $client->request('GET', '/status');
        self::assertInputValueSame('mail_test[recipient]', 'mail-test@example.test');
        $client->submitForm('Send test mail', ['mail_test[recipient]' => 'someone@example.test']);

        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage(), 'To', 'someone@example.test');
        self::assertResponseRedirects('/status');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'accepted the test mail to someone@example.test');
    }
}
