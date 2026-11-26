<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\SyncStatus;
use App\Service\ObserverRelease;
use App\Service\SyncMonitor;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpKernel\Kernel;

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

    public function testTheBrandDropdownLoadsTheSystemInformation(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on', 'SERVER_SOFTWARE' => 'nginx/1.27.0']);
        $client->loginUser($this->createUser('system-info@example.test', 'System Info', 'password'));

        $crawler = $client->request('GET', '/');
        $frame = $crawler->filter('.sidebar-brand turbo-frame#system-info');
        self::assertSame('lazy', $frame->attr('loading'));

        $client->request('GET', (string) $frame->attr('src'));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('dl', 'Symfony '.Kernel::VERSION);
        self::assertSelectorTextContains('dl', 'PHP '.PHP_VERSION);
        self::assertSelectorTextContains('dl', 'Application context test');
        self::assertSelectorTextContains('dl', 'Web server nginx/1.27.0');
        // the syncs' state stays on the status page, only an update is a message here
        self::assertSelectorNotExists('.system-info-update');
    }

    public function testMarksTheBrandWhenANewerObserverIsReleased(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->disableReboot(); // keeps the replaced service for both requests
        $client->loginUser($this->createUser('observer-update@example.test', 'Observer Update', 'password'));

        $release = new ObserverRelease(new MockHttpClient(new JsonMockResponse(['tag_name' => '0.13.0', 'html_url' => 'https://github.com/portrino/cms-observer/releases/tag/0.13.0'])), new ArrayAdapter(), '0.12.3');
        static::getContainer()->set(ObserverRelease::class, $release);

        $client->request('GET', '/');
        self::assertSelectorNotExists('.sidebar-brand-dot');

        $release->sync();
        $client->request('GET', '/');
        self::assertSelectorTextSame('.sidebar-brand-dot', 'Version 0.13.0 available');

        $client->request('GET', '/status/system-info');
        self::assertSelectorTextContains('.system-info-update', 'Version 0.13.0 available');
        self::assertSelectorExists('.system-info-update a[href="https://github.com/portrino/cms-observer/releases/tag/0.13.0"]');
    }

    public function testSendsATestMail(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('mail-test@example.test', 'Mail Test', 'password'));

        $client->request('GET', '/status');
        self::assertInputValueSame('mail_test[recipient]', 'mail-test@example.test');
        $client->submitForm('Send test mail', ['mail_test[recipient]' => 'someone@example.test']);

        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage() ?? self::fail('No email sent'), 'To', 'someone@example.test');
        self::assertResponseRedirects('/status');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'accepted the test mail to someone@example.test');
    }

    public function testShowsABrokenMailerDsnAndItsErrorOnTheTestMail(): void
    {
        // read when the mailer is first used, so it must stay set until the last request
        $previous = $_ENV['MAILER_DSN'] ?? null;
        $_ENV['MAILER_DSN'] = 'no-scheme';
        try {
            $client = static::createClient([], ['HTTPS' => 'on']);
            $client->loginUser($this->createUser('broken-mail@example.test', 'Broken Mail', 'password'));

            $client->request('GET', '/status');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'invalid MAILER_DSN: The mailer DSN must contain a scheme.');

            $client->submitForm('Send test mail', ['mail_test[recipient]' => 'someone@example.test']);
            $client->followRedirect();
            self::assertSelectorTextContains('.alert-danger', 'The mailer DSN must contain a scheme.');
        } finally {
            $_ENV['MAILER_DSN'] = $previous;
        }
    }
}
