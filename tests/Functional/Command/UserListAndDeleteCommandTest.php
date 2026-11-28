<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\User;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserListAndDeleteCommandTest extends KernelTestCase
{
    use CreatesUserTrait;

    public function testListsTheLogins(): void
    {
        $kernel = self::bootKernel();
        $this->createUser('list-a@example.test', 'List A', 'password');

        $tester = new CommandTester(new Application($kernel)->find('app:user:list'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertMatchesRegularExpression('/list-a@example\.test\s+List A\s+no\s+browser/', $tester->getDisplay());
    }

    public function testDeletesALoginWithItsRememberMeTokens(): void
    {
        $kernel = self::bootKernel();
        $this->createUser('stays@example.test', 'Stays', 'password');
        $this->createUser('leaves@example.test', 'Leaves', 'password');
        $connection = static::getContainer()->get(Connection::class);
        $connection->insert('rememberme_token', ['series' => 'series-leaves', 'value' => 'v', 'lastUsed' => '2026-11-28 10:00:00', 'class' => '', 'username' => 'leaves@example.test']);
        $tester = new CommandTester(new Application($kernel)->find('app:user:delete'));

        $tester->setInputs(['no']);
        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'leaves@example.test']));
        self::assertNotNull($this->user('leaves@example.test'), 'kept when not confirmed');

        $tester->setInputs(['yes']);
        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'leaves@example.test']));
        self::assertNull($this->user('leaves@example.test'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM rememberme_token WHERE username = ?', ['leaves@example.test']));
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'nobody@example.test']));
    }

    public function testTheLastLoginStays(): void
    {
        $kernel = self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($entityManager->getRepository(User::class)->findAll() as $user) {
            $entityManager->remove($user);
        }
        $entityManager->flush();
        $this->createUser('only@example.test', 'Only', 'password');

        $tester = new CommandTester(new Application($kernel)->find('app:user:delete'));

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'only@example.test', '--force' => true]));
        self::assertStringContainsString('The last login can\'t be deleted', $tester->getDisplay());
        self::assertNotNull($this->user('only@example.test'));
    }

    private function user(string $email): ?User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }
}
