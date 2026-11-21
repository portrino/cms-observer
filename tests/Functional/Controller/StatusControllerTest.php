<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\SyncStatus;
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

        $client->request('GET', '/status');
        self::assertSelectorTextContains('tr:has(code:contains("app:sync-endoflife-releases")) .badge', 'Overdue');
    }
}
