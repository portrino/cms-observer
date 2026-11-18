<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SecurityNotifier;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SecurityNotifierTest extends KernelTestCase
{
    use CreatesUserTrait;

    public function testFlushSendsOneSummaryEmailToEveryUser(): void
    {
        $this->createUser('notify-a@example.test', 'Notify A', 'password');
        $this->createUser('notify-b@example.test', 'Notify B', 'password');
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');

        $notifier = static::getContainer()->get(SecurityNotifier::class);
        $notifier->addFinding($siteA, 'PHP 8.4.24', 'PHP version is missing security fixes (fixed in 8.4.27).');
        $notifier->addFinding($siteB, 'TYPO3 13.4.0', 'Core version is insecure.');
        $notifier->flush();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailHeaderSame($email, 'Subject', '[CMS Observer] 2 new security findings');

        $recipients = array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo());
        self::assertContains('notify-a@example.test', $recipients);
        self::assertContains('notify-b@example.test', $recipients);

        self::assertEmailHtmlBodyContains($email, 'Site A');
        self::assertEmailHtmlBodyContains($email, 'PHP version is missing security fixes (fixed in 8.4.27).');
        self::assertEmailHtmlBodyContains($email, 'Site B');
        self::assertEmailHtmlBodyContains($email, '/projects/'.$siteA->getId());
    }

    public function testFlushWithoutFindingsSendsNothing(): void
    {
        $this->createUser('notify-none@example.test', 'Notify None', 'password');

        static::getContainer()->get(SecurityNotifier::class)->flush();

        self::assertEmailCount(0);
    }

    public function testFindingsAreNotResentByALaterFlush(): void
    {
        $this->createUser('notify-once@example.test', 'Notify Once', 'password');

        $notifier = static::getContainer()->get(SecurityNotifier::class);
        $notifier->addFinding($this->createSite('Site Once'), 'PHP 8.3.14', 'PHP version is missing security fixes (fixed in 8.3.36).');
        $notifier->flush();
        $notifier->flush();

        self::assertEmailCount(1);
        self::assertEmailHeaderSame(self::getMailerMessage(), 'Subject', '[CMS Observer] 1 new security finding');
    }

    /**
     * A broken mailer must not break report ingestion or the sync commands: the error is logged, not thrown.
     */
    public function testMailerFailureIsLoggedNotThrown(): void
    {
        $mailer = self::createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('Connection refused'));

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('findAll')->willReturn([new User('notify-fail@example.test', 'Notify Fail')]);

        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://cms-observer.test/projects/1');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $notifier = new SecurityNotifier($mailer, $userRepository, $urlGenerator, $logger, self::createStub(EntityManagerInterface::class), 'no-reply@example.test');
        $notifier->addFinding($this->createSite('Site Fail'), 'PHP 8.4.24', 'detail');
        $notifier->flush();
    }

    private function createSite(string $name): Site
    {
        $site = new Site($name, CmsType::Typo3, 'https://example.test', hash('sha256', $name));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($site);
        $entityManager->flush();

        return $site;
    }
}
