<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Repository\SiteRepository;
use App\Security\SiteTokenFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SiteTokenCommandsTest extends KernelTestCase
{
    public function testListShowsIdNameAndUrl(): void
    {
        $site = $this->createSite('Listed Site', 'https://listed.test');

        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:site:list'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertMatchesRegularExpression('/\b'.$site->getId().'\s+Listed Site\s+https:\/\/listed\.test/', $tester->getDisplay());
    }

    public function testRegenerateTokenReplacesTheHashAndPrintsTheNewToken(): void
    {
        $site = $this->createSite('Rotated Site');

        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:site:regenerate-token'));
        $tester->setInputs(['yes']);

        self::assertSame(Command::SUCCESS, $tester->execute(['id' => (string) $site->getId()]));
        preg_match('/^([0-9a-f]{64})$/m', $tester->getDisplay(), $match);

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(
            static::getContainer()->get(SiteTokenFactory::class)->hash($match[1] ?? ''),
            static::getContainer()->get(SiteRepository::class)->find($site->getId())?->getTokenHash(),
        );
    }

    public function testRegenerateTokenKeepsTheTokenWhenNotConfirmed(): void
    {
        $site = $this->createSite('Kept Site');

        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:site:regenerate-token'));
        $tester->setInputs(['no']);

        self::assertSame(Command::FAILURE, $tester->execute(['id' => (string) $site->getId()]));

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(hash('sha256', 'Kept Site'), static::getContainer()->get(SiteRepository::class)->find($site->getId())?->getTokenHash());
    }

    public function testRegenerateTokenFailsForAnUnknownId(): void
    {
        self::bootKernel();
        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:site:regenerate-token'));

        self::assertSame(Command::FAILURE, $tester->execute(['id' => '999999']));
        self::assertStringContainsString('No site found', $tester->getDisplay());
    }

    private function createSite(string $name, string $url = 'https://example.test'): Site
    {
        self::bootKernel();
        $site = new Site($name, CmsType::Typo3, $url, hash('sha256', $name));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($site);
        $entityManager->flush();

        return $site;
    }
}
