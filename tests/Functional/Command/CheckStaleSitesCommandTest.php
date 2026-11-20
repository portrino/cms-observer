<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SiteEvent;
use App\Entity\SiteEventType;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CheckStaleSitesCommandTest extends KernelTestCase
{
    use CreatesUserTrait;

    public function testNotifiesAStaleSiteOncePerOutage(): void
    {
        self::bootKernel();
        $this->createUser('stale-check@example.test', 'Stale Check', 'password');
        $stale = $this->createSite('Stale Site', '-3 days');
        $fresh = $this->createSite('Fresh Site', '-1 day');
        $never = $this->createSite('Never Reported Site', null);

        $this->runCheck();

        self::assertEmailCount(1);
        self::assertEmailHtmlBodyContains(self::getMailerMessage(), 'Stale Site');
        self::assertSame(1, $this->stoppedReportingEvents($stale));
        self::assertSame(0, $this->stoppedReportingEvents($fresh));
        self::assertSame(0, $this->stoppedReportingEvents($never));

        // still stale an hour later: no second notification
        $this->runCheck();
        self::assertSame(1, $this->stoppedReportingEvents($stale));

        // reports again, then stops again: a new outage is notified again
        $this->setLastSeenAt($stale, '-2 days -1 hour');
        $this->runCheck();
        self::assertSame(2, $this->stoppedReportingEvents($stale));
    }

    private function runCheck(): void
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:check-stale-sites'));
        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    private function createSite(string $name, ?string $lastSeen): Site
    {
        $site = new Site($name, CmsType::Typo3, 'https://example.test', hash('sha256', $name));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($site);
        $entityManager->flush();

        if (null !== $lastSeen) {
            $this->setLastSeenAt($site, $lastSeen);
        }

        return $site;
    }

    /**
     * recordReport() always stamps "now", so a report in the past is backdated directly.
     * The new date must be after any earlier "stopped reporting" event, like a real report would be.
     */
    private function setLastSeenAt(Site $site, string $lastSeen): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->executeStatement(
            sprintf('UPDATE %s SET last_seen_at = ? WHERE id = ?', $entityManager->getClassMetadata(Site::class)->getTableName()),
            [new \DateTimeImmutable($lastSeen)->format('Y-m-d H:i:s'), $site->getId()],
        );
        $connection->executeStatement(
            sprintf('UPDATE %s SET occurred_at = ? WHERE site_id = ?', $entityManager->getClassMetadata(SiteEvent::class)->getTableName()),
            [new \DateTimeImmutable($lastSeen.' -1 day')->format('Y-m-d H:i:s'), $site->getId()],
        );
        $entityManager->clear();
    }

    private function stoppedReportingEvents(Site $site): int
    {
        return static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(SiteEvent::class)
            ->count(['site' => $site->getId(), 'type' => SiteEventType::StoppedReporting]);
    }
}
